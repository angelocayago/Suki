<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Memory-only fixtures, with uploaded/deleted test assets confined to a temp disk. */
class SellerProfilesTest extends TestCase
{
    private User $seller;
    private string $assetRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite',config('database.default'));
        $this->assertSame(':memory:',DB::connection()->getDatabaseName(),'Refuse fixture writes outside memory.');
        $this->assertEmpty(config('database.connections.sqlite.url'));
        $this->assetRoot=sys_get_temp_dir().'/suki-profile-assets-'.bin2hex(random_bytes(8));
        config(['filesystems.disks.public.root'=>$this->assetRoot]); Storage::forgetDisk('public');
        foreach([
            'users'=>'id INTEGER PRIMARY KEY, name TEXT, first_name TEXT, last_name TEXT, middle_initial TEXT, email TEXT, phone TEXT UNIQUE, password TEXT, role TEXT, status TEXT, government_id TEXT, is_suspended INTEGER DEFAULT 0, verification_required_at TEXT, email_verified_at TEXT, phone_verified_at TEXT, remember_token TEXT, created_at TEXT, updated_at TEXT',
            'sellers'=>'id INTEGER PRIMARY KEY, user_id INTEGER, name TEXT, slug TEXT, description TEXT, logo_path TEXT, banner_path TEXT, category_id INTEGER, pickup_address_id INTEGER, status TEXT, valid_id_path TEXT, business_permit_path TEXT, commission_bps INTEGER, created_at TEXT, updated_at TEXT',
            'categories'=>'id INTEGER PRIMARY KEY, name TEXT',
            'addresses'=>'id INTEGER PRIMARY KEY, user_id INTEGER, label TEXT, recipient TEXT, phone TEXT, line1 TEXT, region TEXT, region_code TEXT, province TEXT, province_code TEXT, city TEXT, city_municipality_code TEXT, barangay TEXT, barangay_code TEXT, postal_code TEXT, is_default INTEGER, created_at TEXT, updated_at TEXT',
            'notifications'=>'id TEXT PRIMARY KEY, type TEXT, notifiable_type TEXT, notifiable_id INTEGER, data TEXT, read_at TEXT, created_at TEXT, updated_at TEXT',
        ] as $table=>$columns) DB::statement('CREATE TABLE '.$table.' ('.$columns.')');
        $this->seller=User::forceCreate(['id'=>11,'first_name'=>'Lance','last_name'=>'Owner','name'=>'Lance Owner','email'=>'owned@example.test','phone'=>'09171111111','password'=>'CurrentPass1!','role'=>'seller','status'=>'active','government_id'=>'PRIVATE-USER-ID','email_verified_at'=>'2026-01-01','phone_verified_at'=>'2026-01-01']);
        foreach([22=>'seller',33=>'buyer',44=>'admin',55=>'logistics',66=>'rider'] as $id=>$role) DB::table('users')->insert(['id'=>$id,'name'=>'Other '.$role,'first_name'=>'Other','last_name'=>ucfirst($role),'email'=>$role.'@example.test','phone'=>'091700000'.$id,'password'=>Hash::make('OtherPass1!'),'role'=>$role,'status'=>'active']);
        DB::table('categories')->insert([['id'=>1,'name'=>'Assigned Category'],['id'=>2,'name'=>'Other Category']]);
        DB::table('sellers')->insert([
            ['id'=>101,'user_id'=>11,'name'=>'Owned Store','slug'=>'owned-store','description'=>'Saved shop description','logo_path'=>'seller-store-assets/owned.webp','category_id'=>1,'pickup_address_id'=>1,'status'=>'approved','valid_id_path'=>'PRIVATE-SELLER-ID','business_permit_path'=>'PRIVATE-PERMIT','commission_bps'=>1000,'created_at'=>'2026-01-01'],
            ['id'=>202,'user_id'=>22,'name'=>'FOREIGN STORE','slug'=>'foreign-store','description'=>'Foreign description','logo_path'=>'seller-store-assets/foreign.webp','category_id'=>2,'pickup_address_id'=>2,'status'=>'approved','valid_id_path'=>'FOREIGN-ID','business_permit_path'=>'FOREIGN-PERMIT','commission_bps'=>1000,'created_at'=>'2026-01-01'],
        ]);
        DB::table('addresses')->insert([['id'=>1,'user_id'=>11,'label'=>'Store pickup','line1'=>'Owned street','region_code'=>'R1','province_code'=>'P1','city_municipality_code'=>'C1','barangay_code'=>'B1'],['id'=>2,'user_id'=>22,'label'=>'Other pickup','line1'=>'FOREIGN ADDRESS','region_code'=>'R2','province_code'=>'P2','city_municipality_code'=>'C2','barangay_code'=>'B2']]);
    }

    private function personal(array $overrides=[]): array
    {
        return array_replace(['first_name'=>'Updated','last_name'=>'Owner','middle_initial'=>'U','phone'=>'09171234567'],$overrides);
    }

    public function test_navigation_has_two_active_destinations_no_identity_block_and_preserves_header(): void
    {
        $profile=$this->actingAs($this->seller)->get(route('seller.profile'))->assertOk()->assertSee('My Profile')->assertSee('owned@example.test')
            ->assertSee(route('seller.store.profile'))->assertSee(route('seller.notifications.index'))->assertSee(route('seller.vouchers.index'))
            ->assertSee('id="sellerNotificationBell"',false)->assertSee('id="sellerProfileToggle"',false)->assertSee('data-seller-logout',false)
            ->assertDontSee('<div class="seller-sidebar-account"',false)->assertDontSee('href="'.route('seller.promotions').'"',false);
        $html=$profile->getContent();
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('seller.profile'),'/').'"[^>]*aria-current="page"/',$html);
        $this->assertStringContainsString('sellerSidebarCollapse',$html); $this->assertStringContainsString('sellerSidebarOpen',$html);
        $this->assertStringContainsString('method="POST" data-seller-logout',$html);
        $this->assertSame(2,substr_count($html,'method="POST" data-seller-logout'));
        $this->assertSame(1,substr_count($html,'<dialog id="sellerLogoutDialog"'));
        $this->assertStringNotContainsString('data-confirm-logout',$html);
        $this->assertStringContainsString('You will be signed out of your seller account.',$html);
        $this->assertStringContainsString('action="'.route('seller.store.password.update').'"',$html);
        $this->assertStringNotContainsString('name="email"',$html); $this->assertStringNotContainsString('name="profile_photo"',$html);
        $this->assertStringNotContainsString($this->seller->getAuthPassword(),$html); $this->assertStringNotContainsString('PRIVATE-USER-ID',$html);
        $store=$this->get(route('seller.store.profile'))->assertOk()->assertSee('Store Profile');
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('seller.store.profile'),'/').'"[^>]*aria-current="page"/',$store->getContent());
        if($dir=getenv('SELLER_PROFILE_CHECK_DIR')) { file_put_contents($dir.'/personal.html',$html); file_put_contents($dir.'/store.html',$store->getContent()); }
    }

    public function test_existing_post_logout_ends_session_and_regenerates_csrf_token(): void
    {
        $this->actingAs($this->seller)->withSession(['private_marker'=>'secret','_token'=>'before-logout-token']);
        $this->post(route('logout'),['_token'=>'before-logout-token'])
            ->assertRedirect(route('landing'))->assertSessionMissing('private_marker')
            ->assertSessionHas('success','You have been logged out successfully.');
        $this->assertGuest();
        $this->assertNotSame('before-logout-token',session()->token());
        $this->get(route('seller.profile'))->assertRedirect(route('login'));
    }

    public function test_store_profile_reads_canonical_shop_owner_address_and_photo_not_stale_session(): void
    {
        $response=$this->actingAs($this->seller)->withSession(['seller_profile'=>['shop_name'=>'POISON SHOP','seller_name'=>'POISON USER','phone'=>'POISON PHONE','description'=>'POISON DESCRIPTION','profile_photo'=>'POISON-PHOTO.webp','address'=>'POISON ADDRESS']])->get(route('seller.store.profile'))->assertOk();
        $response->assertSee('Owned Store')->assertSee('Saved shop description')->assertSee('09171111111')->assertSee('owned@example.test')->assertSee('Owned street')->assertSee('seller-store-assets/owned.webp')->assertSee('Assigned Category')
            ->assertDontSee('POISON')->assertDontSee('FOREIGN ADDRESS')->assertDontSee('PRIVATE-SELLER-ID')->assertDontSee('PRIVATE-PERMIT')->assertDontSee('name="current_password"',false)
            ->assertSee(route('seller.profile').'#security')->assertSee('name="store_name"',false);
    }

    public function test_personal_updates_use_existing_whitelist_and_cannot_modify_another_account(): void
    {
        $before=\Illuminate\Support\Arr::only($this->seller->getAttributes(),['email','role','status','government_id','email_verified_at','phone_verified_at','password']);
        $other=User::find(22)->getAttributes();
        $this->actingAs($this->seller)->from(route('seller.profile'))->patch(route('seller.profile.update'),$this->personal(['first_name'=>' Updated ','user_id'=>22,'seller_id'=>202,'role'=>'admin','status'=>'inactive','email'=>'hijack@example.test','email_verified_at'=>null,'phone_verified_at'=>null,'government_id'=>'REPLACED','password'=>'InjectedPass1!']))->assertSessionHasNoErrors()->assertRedirect(route('seller.profile'));
        $this->seller->refresh();
        $this->assertSame('Updated Owner',$this->seller->name); $this->assertSame('09171234567',$this->seller->phone);
        $this->assertSame($before,\Illuminate\Support\Arr::only($this->seller->getAttributes(),array_keys($before))); $this->assertSame($other,User::find(22)->getAttributes());
        $this->assertSame('Owned Store',DB::table('sellers')->where('id',101)->value('name'));
        $this->assertSame(6,User::count()); $this->assertSame(2,DB::table('sellers')->count());
    }

    public function test_personal_validation_and_old_input_are_preserved(): void
    {
        foreach([['first_name'=>''],['last_name'=>str_repeat('x',101)],['middle_initial'=>'ABCDE'],['phone'=>'09170000022']] as $override) $this->actingAs($this->seller)->from(route('seller.profile'))->patch(route('seller.profile.update'),$this->personal($override))->assertSessionHasErrors(array_key_first($override));
        $this->get(route('seller.profile'))->assertOk()->assertSee('Please review your details.')->assertSee('value="Updated"',false);
        $this->assertSame('Lance Owner',$this->seller->fresh()->name);
    }

    public function test_existing_password_checks_are_kept_and_success_returns_to_my_profile(): void
    {
        $original=$this->seller->getAuthPassword();
        foreach([
            ['current_password'=>'WrongPass1!','password'=>'NewPass123!','password_confirmation'=>'NewPass123!'],
            ['current_password'=>'CurrentPass1!','password'=>'short','password_confirmation'=>'short'],
            ['current_password'=>'CurrentPass1!','password'=>'NewPass123!','password_confirmation'=>'Mismatch123!'],
            ['current_password'=>'CurrentPass1!','password'=>'CurrentPass1!','password_confirmation'=>'CurrentPass1!'],
        ] as $data) $this->actingAs($this->seller)->from(route('seller.profile'))->post(route('seller.store.password.update'),$data)->assertSessionHasErrors();
        $this->assertSame($original,$this->seller->fresh()->getAuthPassword());
        $other=User::find(22)->getAuthPassword();
        $this->post(route('seller.store.password.update'),['current_password'=>'CurrentPass1!','password'=>'NewPass123!','password_confirmation'=>'NewPass123!','user_id'=>22])->assertSessionHasNoErrors()->assertRedirect(route('seller.profile').'#security');
        $this->assertTrue(Hash::check('NewPass123!',$this->seller->fresh()->getAuthPassword()));
        $this->assertSame($other,User::find(22)->getAuthPassword());
    }

    public function test_store_update_keeps_existing_owner_rules_and_registered_business_fields(): void
    {
        $original=DB::table('sellers')->where('id',101)->first();
        $this->actingAs($this->seller)->post(route('seller.store.profile.update'),['store_name'=>'Updated Shop','phone'=>'09172223333','description'=>'Updated description','seller_id'=>202,'category_id'=>2,'status'=>'pending','commission_bps'=>9999,'valid_id_path'=>'REPLACED'])->assertSessionHasNoErrors()->assertRedirect(route('seller.store.profile').'#shop-profile');
        $saved=DB::table('sellers')->where('id',101)->first();
        $this->assertSame('Updated Shop',$saved->name); $this->assertSame('Updated description',$saved->description);
        foreach(['category_id','status','commission_bps','valid_id_path','business_permit_path'] as $key) $this->assertSame($original->{$key},$saved->{$key});
        $this->assertSame('FOREIGN STORE',DB::table('sellers')->where('id',202)->value('name'));
        $this->assertSame('09172223333',$this->seller->fresh()->phone);
        $this->get(route('seller.store.profile'))->assertOk()->assertSee('Updated Shop')->assertSee('Updated description')->assertSee('09172223333');
    }

    public function test_shop_photo_upload_validation_and_owner_removal_use_the_existing_asset_paths(): void
    {
        Storage::disk('public')->put('seller-store-assets/owned.webp','owned fixture');
        Storage::disk('public')->put('seller-store-assets/foreign.webp','foreign fixture');
        $this->actingAs($this->seller)->post(route('seller.store.profile.update'),['phone'=>'09171111111','profile_photo'=>UploadedFile::fake()->createWithContent('invalid.php','not an image')])->assertSessionHasErrors('profile_photo');
        $this->assertSame('seller-store-assets/owned.webp',DB::table('sellers')->where('id',101)->value('logo_path'));
        $image=UploadedFile::fake()->createWithContent('logo.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        $this->post(route('seller.store.profile.update'),['phone'=>'09171111111','profile_photo'=>$image])->assertSessionHasNoErrors();
        $path=DB::table('sellers')->where('id',101)->value('logo_path');
        $this->assertStringStartsWith('seller-store-assets/',$path); $this->assertFileExists(Storage::disk('public')->path($path));
        $this->assertFalse(Storage::disk('public')->exists('seller-store-assets/owned.webp'));
        $this->withSession(['seller_profile'=>['profile_photo'=>'seller-store-assets/foreign.webp']])->post(route('seller.store.profile.photo.remove'),['seller_id'=>202])->assertRedirect(route('seller.store.profile').'#shop-profile');
        $this->assertNull(DB::table('sellers')->where('id',101)->value('logo_path')); $this->assertFalse(Storage::disk('public')->exists($path));
        $this->assertTrue(Storage::disk('public')->exists('seller-store-assets/foreign.webp')); $this->assertSame('seller-store-assets/foreign.webp',DB::table('sellers')->where('id',202)->value('logo_path'));
    }

    public function test_store_address_still_uses_canonical_resolver_and_cannot_select_foreign_address(): void
    {
        $this->mock(\App\Services\PhilippineAddressService::class)->shouldReceive('resolve')->once()->andReturn(['region'=>'Resolved Region','region_code'=>'R1','province'=>'Resolved Province','province_code'=>'P1','municipality'=>'Resolved City','city_municipality_code'=>'C1','barangay'=>'Resolved Barangay','barangay_code'=>'B1']);
        $this->actingAs($this->seller)->post(route('seller.store.address.update'),['region'=>'R1','province'=>'P1','municipality'=>'C1','barangay'=>'B1','address'=>'New owned street','address_id'=>2])->assertSessionHasNoErrors()->assertRedirect(route('seller.store.profile').'#pickup-address');
        $this->assertSame('New owned street',DB::table('addresses')->where('id',1)->value('line1'));
        $this->assertSame('FOREIGN ADDRESS',DB::table('addresses')->where('id',2)->value('line1'));
        $this->assertSame(1,DB::table('sellers')->where('id',101)->value('pickup_address_id'));
    }

    public function test_auth_guards_profiles_for_other_roles_and_existing_routes_are_preserved(): void
    {
        foreach(['seller.profile','seller.profile.update'] as $name) foreach(['web','auth','not_suspended','approved','role:seller'] as $guard) $this->assertContains($guard,app('router')->getRoutes()->getByName($name)->gatherMiddleware());
        $this->get(route('seller.profile'))->assertRedirect(route('login'));
        $this->actingAs(User::find(33))->get(route('seller.profile'))->assertForbidden();
        $this->post(route('seller.store.profile.photo.remove'))->assertForbidden();
        $this->actingAs($this->seller); DB::table('users')->where('id',11)->update(['is_suspended'=>1]);
        $this->seller->refresh();
        $this->get(route('seller.profile'))->assertForbidden();
        $this->seller->refresh();
        foreach([44=>'admin',55=>'logistics',66=>'rider'] as $id=>$area) {
            $this->actingAs(User::find($id))->from('/')->patch(route($area.'.profile.update'),['first_name'=>'Preserved','last_name'=>'Profile','phone'=>'091799999'.$id])->assertSessionHasNoErrors()->assertRedirect('/');
            $this->assertSame('Preserved Profile',User::find($id)->name); $this->assertSame($area,User::find($id)->role);
            $this->assertSame($area.'/profile',app('router')->getRoutes()->getByName($area.'.profile')->uri());
        }
        $this->assertSame('seller/store-profile',app('router')->getRoutes()->getByName('seller.store.profile')->uri());
        $this->assertSame(['POST'],app('router')->getRoutes()->getByName('seller.store.password.update')->methods());
    }
}
