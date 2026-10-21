import 'package:flutter/material.dart';
import '../../styles/buyer_style.dart';

class BuyerHomePage extends StatefulWidget {
  const BuyerHomePage({super.key});

  @override
  State<BuyerHomePage> createState() => _BuyerHomePageState();
}

class _BuyerHomePageState extends State<BuyerHomePage> {
  int selectedIndex = 0;

  final categories = [
    {"name": "Food", "icon": Icons.fastfood_outlined},

    {"name": "Grocery", "icon": Icons.shopping_cart_outlined},

    {"name": "Fashion", "icon": Icons.checkroom_outlined},

    {"name": "Others", "icon": Icons.grid_view},
  ];

  final products = [
    {
      "name": "Fresh Coffee",
      "price": "₱120",
      "image": "assets/images/product1.png",
    },

    {
      "name": "Local Snacks",
      "price": "₱80",
      "image": "assets/images/product2.png",
    },

    {
      "name": "Daily Essentials",
      "price": "₱250",
      "image": "assets/images/product3.png",
    },
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: BuyerStyle.background,

      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(18),

          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              // HEADER
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,

                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,

                    children: [
                      const Text("Hello Buyer 👋", style: BuyerStyle.greeting),

                      const Text(
                        "Welcome to Mobile Suki",
                        style: BuyerStyle.appTitle,
                      ),
                    ],
                  ),

                  CircleAvatar(
                    radius: 22,

                    backgroundColor: BuyerStyle.primary,

                    child: const Icon(Icons.person, color: Colors.white),
                  ),
                ],
              ),

              const SizedBox(height: 20),

              // SEARCH
              TextField(decoration: BuyerStyle.searchDecoration()),

              const SizedBox(height: 20),

              // BANNER
              Container(
                width: double.infinity,

                height: 120,

                decoration: BuyerStyle.banner(),

                padding: const EdgeInsets.all(20),

                child: const Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    Text(
                      "Shop local products",
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                      ),
                    ),

                    SizedBox(height: 8),

                    Text(
                      "Affordable items from trusted sellers.",
                      style: TextStyle(color: Colors.white, fontSize: 12),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 22),

              // CATEGORY
              const Text("Categories", style: BuyerStyle.sectionTitle),

              const SizedBox(height: 12),

              SizedBox(
                height: 75,

                child: ListView.builder(
                  scrollDirection: Axis.horizontal,

                  itemCount: categories.length,

                  itemBuilder: (context, index) {
                    return Container(
                      width: 80,

                      margin: const EdgeInsets.only(right: 10),

                      decoration: BuyerStyle.categoryCard(),

                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,

                        children: [
                          Icon(
                            categories[index]["icon"] as IconData,

                            color: BuyerStyle.primary,
                          ),

                          const SizedBox(height: 5),

                          Text(
                            categories[index]["name"] as String,

                            style: const TextStyle(fontSize: 11),
                          ),
                        ],
                      ),
                    );
                  },
                ),
              ),

              const SizedBox(height: 22),

              // PRODUCTS
              const Text("Featured Products", style: BuyerStyle.sectionTitle),

              const SizedBox(height: 12),

              GridView.builder(
                shrinkWrap: true,

                physics: const NeverScrollableScrollPhysics(),

                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 2,

                  childAspectRatio: 0.75,

                  crossAxisSpacing: 12,

                  mainAxisSpacing: 12,
                ),

                itemCount: products.length,

                itemBuilder: (context, index) {
                  return Container(
                    decoration: BuyerStyle.productCard(),

                    padding: const EdgeInsets.all(10),

                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,

                      children: [
                        Expanded(
                          child: Container(
                            width: double.infinity,

                            decoration: BoxDecoration(
                              color: Colors.grey.shade100,

                              borderRadius: BorderRadius.circular(10),
                            ),

                            child: const Icon(
                              Icons.image,
                              size: 45,
                              color: Colors.grey,
                            ),
                          ),
                        ),

                        const SizedBox(height: 8),

                        Text(
                          products[index]["name"]!,

                          style: BuyerStyle.productName,
                        ),

                        Text(
                          products[index]["price"]!,

                          style: BuyerStyle.price,
                        ),
                      ],
                    ),
                  );
                },
              ),
            ],
          ),
        ),
      ),

      // BOTTOM NAVIGATION
      bottomNavigationBar: BottomNavigationBar(
        currentIndex: selectedIndex,

        selectedItemColor: BuyerStyle.primary,

        onTap: (value) {
          setState(() {
            selectedIndex = value;
          });
        },

        items: const [
          BottomNavigationBarItem(
            icon: Icon(Icons.home_outlined),

            label: "Home",
          ),

          BottomNavigationBarItem(
            icon: Icon(Icons.shopping_cart_outlined),

            label: "Cart",
          ),

          BottomNavigationBarItem(
            icon: Icon(Icons.receipt_long_outlined),

            label: "Orders",
          ),

          BottomNavigationBarItem(
            icon: Icon(Icons.person_outline),

            label: "Profile",
          ),
        ],
      ),
    );
  }
}
