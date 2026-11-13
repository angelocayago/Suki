# Laravel PHP upload files

Upload each PHP file to the matching path in your deployed Laravel project, replacing existing files where indicated.

| File in this folder | Laravel destination |
| --- | --- |
| `routes_api.php` | `routes/api.php` (replace) |
| `Api_AuthController.php` | `app/Http/Controllers/Api/AuthController.php` (replace) |
| `Api_BuyerOrdersController.php` | `app/Http/Controllers/Api/BuyerOrdersController.php` (new) |
| `Api_RiderController.php` | `app/Http/Controllers/Api/RiderController.php` (new) |
| `Buyer_CartController.php` | `app/Http/Controllers/Buyer/CartController.php` (replace) |
| `Middleware_EnsureRiderApi.php` | `app/Http/Middleware/EnsureRiderApi.php` (new) |

The deployed app must serve the `/api` routes. No migration is included.
