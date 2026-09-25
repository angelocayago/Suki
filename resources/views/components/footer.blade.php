<footer
    class="mt-14
           border-t border-[#173F35]/10
           bg-[#173F35]
           text-white"
>

    <div
        class="mx-auto max-w-[1500px]
               px-6 py-12
               lg:px-10 lg:py-14"
    >

        <div
            class="grid gap-10
                   lg:grid-cols-[1.35fr_.75fr_.75fr_.9fr]"
        >


            {{-- BRAND --}}
            <div class="max-w-md">

                <a
                    href="{{ route('landing') }}"
                    class="flex items-center gap-3"
                >

                    <div
                        class="flex h-11 w-11
                               items-center justify-center
                               rounded-xl
                               bg-white/10"
                    >

                        <img
                            src="{{ asset('images/suki-logo.png') }}"
                            alt="SUKI SHOP"
                            class="h-9 w-9 object-contain"
                        >

                    </div>


                    <div>

                        <p
                            class="text-base font-bold
                                   tracking-[-0.03em]"
                        >
                            SUKI SHOP
                        </p>

                        <p
                            class="mt-0.5
                                   text-[9px]
                                   uppercase tracking-[.16em]
                                   text-white/50"
                        >
                            Connected Marketplace
                        </p>

                    </div>

                </a>


                <p
                    class="mt-5
                           text-sm
                           leading-7
                           text-white/60"
                >
                    A connected marketplace for buyers, sellers,
                    riders, and logistics partners—from discovery
                    to doorstep.
                </p>

            </div>


            {{-- MARKETPLACE --}}
            <div>

                <p
                    class="text-[10px]
                           font-semibold
                           uppercase tracking-[.16em]
                           text-white/45"
                >
                    Marketplace
                </p>


                <div
                    class="mt-4 space-y-3
                           text-sm
                           text-white/65"
                >

                    <a
                        href="{{ route('buyer.shop') }}"
                        class="block hover:text-white"
                    >
                        Shop products
                    </a>

                    <a
                        href="{{ route('seller.register') }}"
                        class="block hover:text-white"
                    >
                        Become a seller
                    </a>

                    <a
                        href="{{ route('rider.apply') }}"
                        class="block hover:text-white"
                    >
                        Become a rider
                    </a>

                </div>

            </div>


            {{-- ACCOUNT --}}
            <div>

                <p
                    class="text-[10px]
                           font-semibold
                           uppercase tracking-[.16em]
                           text-white/45"
                >
                    Account
                </p>


                <div
                    class="mt-4 space-y-3
                           text-sm
                           text-white/65"
                >

                    <a
                        href="{{ route('buyer.account') }}"
                        class="block hover:text-white"
                    >
                        My account
                    </a>

                    <a
                        href="{{ route('buyer.my-orders') }}"
                        class="block hover:text-white"
                    >
                        My orders
                    </a>

                    <a
                        href="{{ route('buyer.wishlist') }}"
                        class="block hover:text-white"
                    >
                        Wishlist
                    </a>

                </div>

            </div>


            {{-- INFO CARD --}}
            <div
                class="rounded-2xl
                       border border-white/10
                       bg-white/[.06]
                       p-5"
            >

                <p class="text-sm font-semibold">
                    Built around everyday connections.
                </p>


                <p
                    class="mt-2
                           text-xs
                           leading-6
                           text-white/55"
                >
                    One consistent experience for shopping,
                    selling, fulfillment, and delivery.
                </p>


                <a
                    href="{{ route('landing') }}#how-it-works"
                    class="mt-4
                           inline-flex items-center gap-2
                           text-xs font-semibold
                           text-white"
                >

                    See how SUKI works

                    <i
                        data-lucide="arrow-up-right"
                        class="h-3.5 w-3.5"
                    ></i>

                </a>

            </div>

        </div>


        {{-- BOTTOM --}}
        <div
            class="mt-10
                   flex flex-col gap-3
                   border-t border-white/10
                   pt-5
                   text-[11px]
                   text-white/45
                   sm:flex-row
                   sm:items-center
                   sm:justify-between"
        >

            <p>
                © {{ date('Y') }} SUKI SHOP. All rights reserved.
            </p>

            <p>
                Shop. Sell. Deliver. Together.
            </p>

        </div>

    </div>

</footer>