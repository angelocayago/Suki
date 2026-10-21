import 'package:flutter/material.dart';

import '../../styles/rider_style.dart';

class RiderHomePage extends StatefulWidget {
  const RiderHomePage({super.key});

  @override
  State<RiderHomePage> createState() => _RiderHomePageState();
}

class _RiderHomePageState extends State<RiderHomePage> {
  bool isOnline = true;

  int selectedIndex = 0;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: RiderStyle.background,

      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(18),

          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              // =========================
              // HEADER
              // =========================
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,

                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,

                    children: [
                      const Text("Hello Rider 👋", style: RiderStyle.greeting),

                      const Text("Rider Dashboard", style: RiderStyle.appTitle),
                    ],
                  ),

                  CircleAvatar(
                    radius: 22,

                    backgroundColor: RiderStyle.primary,

                    child: const Icon(
                      Icons.delivery_dining,

                      color: Colors.white,
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 20),

              // =========================
              // ONLINE STATUS
              // =========================
              Container(
                padding: const EdgeInsets.all(15),

                decoration: RiderStyle.statusCard(),

                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,

                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,

                      children: [
                        const Text(
                          "Delivery Status",

                          style: RiderStyle.cardTitle,
                        ),

                        const SizedBox(height: 5),

                        Text(
                          isOnline ? "You are Online" : "You are Offline",

                          style: TextStyle(
                            color: isOnline
                                ? RiderStyle.success
                                : RiderStyle.danger,

                            fontWeight: FontWeight.w700,

                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),

                    Switch(
                      value: isOnline,

                      activeColor: RiderStyle.primary,

                      onChanged: (value) {
                        setState(() {
                          isOnline = value;
                        });
                      },
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 22),

              // =========================
              // CURRENT DELIVERY
              // =========================
              const Text("Current Delivery", style: RiderStyle.sectionTitle),

              const SizedBox(height: 10),

              Container(
                padding: const EdgeInsets.all(16),

                decoration: RiderStyle.cardDecoration(),

                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,

                      children: [
                        const Text(
                          "Order #MS10234",

                          style: RiderStyle.cardTitle,
                        ),

                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 10,

                            vertical: 5,
                          ),

                          decoration: BoxDecoration(
                            color: Colors.orange.shade100,

                            borderRadius: BorderRadius.circular(20),
                          ),

                          child: const Text(
                            "On Delivery",

                            style: TextStyle(
                              fontSize: 10,

                              color: Colors.orange,

                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 15),

                    const Text("Pickup Location", style: RiderStyle.normalText),

                    const Text(
                      "Mobile Suki Warehouse",

                      style: RiderStyle.cardTitle,
                    ),

                    const SizedBox(height: 12),

                    const Text(
                      "Customer Address",

                      style: RiderStyle.normalText,
                    ),

                    const Text(
                      "Barangay San Isidro",

                      style: RiderStyle.cardTitle,
                    ),

                    const SizedBox(height: 15),

                    SizedBox(
                      width: double.infinity,

                      height: 42,

                      child: ElevatedButton(
                        style: RiderStyle.primaryButton(),

                        onPressed: () {
                          Navigator.pushNamed(context, '/delivery-details');
                        },

                        child: const Text("View Delivery"),
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 22),

              // =========================
              // QUICK ACTIONS
              // =========================
              const Text("Quick Actions", style: RiderStyle.sectionTitle),

              const SizedBox(height: 10),

              Row(
                children: [
                  _actionCard(
                    Icons.local_shipping_outlined,

                    "Deliveries",

                    '/rider-deliveries',
                  ),

                  const SizedBox(width: 10),

                  _actionCard(
                    Icons.qr_code_scanner,

                    "Scan Parcel",

                    '/rider-scanner',
                  ),
                ],
              ),

              const SizedBox(height: 12),

              Row(
                children: [
                  _actionCard(
                    Icons.wallet_outlined,

                    "Earnings",

                    '/rider-earnings',
                  ),

                  const SizedBox(width: 10),

                  _actionCard(
                    Icons.person_outline,

                    "Profile",

                    '/rider-profile',
                  ),
                ],
              ),
            ],
          ),
        ),
      ),

      bottomNavigationBar: BottomNavigationBar(
        currentIndex: selectedIndex,

        selectedItemColor: RiderStyle.primary,

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
            icon: Icon(Icons.local_shipping_outlined),

            label: "Deliveries",
          ),

          BottomNavigationBarItem(
            icon: Icon(Icons.wallet_outlined),

            label: "Earnings",
          ),

          BottomNavigationBarItem(
            icon: Icon(Icons.person_outline),

            label: "Profile",
          ),
        ],
      ),
    );
  }

  Widget _actionCard(IconData icon, String title, String route) {
    return Expanded(
      child: GestureDetector(
        onTap: () {
          Navigator.pushNamed(context, route);
        },

        child: Container(
          height: 95,

          decoration: RiderStyle.cardDecoration(),

          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,

            children: [
              Icon(icon, color: RiderStyle.primary),

              const SizedBox(height: 8),

              Text(title, style: RiderStyle.cardTitle),
            ],
          ),
        ),
      ),
    );
  }
}
