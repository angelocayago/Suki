import 'package:flutter/material.dart';

import '../../styles/rider_style.dart';
import '../../widgets/theme_toggle_button.dart';

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
    Theme.of(context);
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
                      Text("Hello Rider 👋", style: RiderStyle.greeting),

                      Text("Rider Dashboard", style: RiderStyle.appTitle),
                    ],
                  ),

                  Row(
                    children: [
                      const ThemeToggleButton(),
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
                ],
              ),

              SizedBox(height: 20),

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
                        Text(
                          "Delivery Status",

                          style: RiderStyle.cardTitle,
                        ),

                        SizedBox(height: 5),

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

              SizedBox(height: 22),

              // =========================
              // CURRENT DELIVERY
              // =========================
              Text("Current Delivery", style: RiderStyle.sectionTitle),

              SizedBox(height: 10),

              Container(
                padding: const EdgeInsets.all(16),

                decoration: RiderStyle.cardDecoration(),

                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,

                      children: [
                        Text(
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

                          child: Text(
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

                    SizedBox(height: 15),

                    Text("Pickup Location", style: RiderStyle.normalText),

                    Text(
                      "Mobile Suki Warehouse",

                      style: RiderStyle.cardTitle,
                    ),

                    SizedBox(height: 12),

                    Text(
                      "Customer Address",

                      style: RiderStyle.normalText,
                    ),

                    Text(
                      "Barangay San Isidro",

                      style: RiderStyle.cardTitle,
                    ),

                    SizedBox(height: 15),

                    SizedBox(
                      width: double.infinity,

                      height: 42,

                      child: ElevatedButton(
                        style: RiderStyle.primaryButton(),

                        onPressed: () {
                          Navigator.pushNamed(context, '/delivery-details');
                        },

                        child: Text("View Delivery"),
                      ),
                    ),
                  ],
                ),
              ),

              SizedBox(height: 22),

              // =========================
              // QUICK ACTIONS
              // =========================
              Text("Quick Actions", style: RiderStyle.sectionTitle),

              SizedBox(height: 10),

              Row(
                children: [
                  _actionCard(
                    Icons.local_shipping_outlined,

                    "Deliveries",

                    '/rider-deliveries',
                  ),

                  SizedBox(width: 10),

                  _actionCard(
                    Icons.qr_code_scanner,

                    "Scan Parcel",

                    '/rider-scanner',
                  ),
                ],
              ),

              SizedBox(height: 12),

              Row(
                children: [
                  _actionCard(
                    Icons.wallet_outlined,

                    "Earnings",

                    '/rider-earnings',
                  ),

                  SizedBox(width: 10),

                  _actionCard(
                    Icons.person_outline,

                    "Profile",

                    '/rider-profile',
                  ),
                ],
              ),

              SizedBox(height: 12),

              Row(
                children: [
                  _actionCard(
                    Icons.history,
                    'History',
                    '/rider/history',
                  ),
                  SizedBox(width: 10),
                  _actionCard(
                    Icons.bar_chart_outlined,
                    'Reports',
                    '/rider/reports',
                  ),
                ],
              ),

              SizedBox(height: 12),

              Row(
                children: [
                  _actionCard(
                    Icons.payments_outlined,
                    'COD Remittance',
                    '/rider/cod-remittances',
                  ),
                  SizedBox(width: 10),
                  _actionCard(
                    Icons.account_balance_wallet_outlined,
                    'Money Report',
                    '/rider/money',
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
          const routes = [
            '/rider/dashboard',
            '/rider/deliveries',
            '/rider/earnings',
            '/rider-profile',
          ];
          if (value == 0) {
            setState(() => selectedIndex = value);
            return;
          }
          Navigator.pushReplacementNamed(context, routes[value]);
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

              SizedBox(height: 8),

              Text(title, style: RiderStyle.cardTitle),
            ],
          ),
        ),
      ),
    );
  }
}
