import 'package:flutter/material.dart';

import '../../styles/logistics_style.dart';

class LogisticsHomePage extends StatefulWidget {
  const LogisticsHomePage({super.key});

  @override
  State<LogisticsHomePage> createState() => _LogisticsHomePageState();
}

class _LogisticsHomePageState extends State<LogisticsHomePage> {
  int selectedIndex = 0;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      resizeToAvoidBottomInset: true,

      backgroundColor: LogisticsStyle.background,

      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            return SingleChildScrollView(
              physics: const BouncingScrollPhysics(),

              padding: const EdgeInsets.all(18),

              child: ConstrainedBox(
                constraints: BoxConstraints(minHeight: constraints.maxHeight),

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
                            const Text(
                              "Hello Logistics 👋",

                              style: LogisticsStyle.greeting,
                            ),

                            const SizedBox(height: 4),

                            const Text(
                              "Shipment Dashboard",

                              style: LogisticsStyle.appTitle,
                            ),
                          ],
                        ),

                        CircleAvatar(
                          radius: 22,

                          backgroundColor: LogisticsStyle.primary,

                          child: const Icon(
                            Icons.inventory_2_outlined,

                            color: Colors.white,
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 22),

                    // =========================
                    // SUMMARY
                    // =========================
                    const Text(
                      "Shipment Summary",

                      style: LogisticsStyle.sectionTitle,
                    ),

                    const SizedBox(height: 12),

                    Row(
                      children: [
                        Expanded(
                          child: _buildStat(
                            "25",

                            "Pending",

                            Icons.pending_actions,
                          ),
                        ),

                        const SizedBox(width: 10),

                        Expanded(
                          child: _buildStat(
                            "18",

                            "Processing",

                            Icons.inventory_outlined,
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 12),

                    Row(
                      children: [
                        Expanded(
                          child: _buildStat(
                            "42",

                            "Delivered",

                            Icons.check_circle_outline,
                          ),
                        ),

                        const SizedBox(width: 10),

                        Expanded(
                          child: _buildStat(
                            "5",

                            "Riders",

                            Icons.delivery_dining,
                          ),
                        ),
                      ],
                    ),

                    const SizedBox(height: 22),

                    // =========================
                    // QUICK ACTIONS
                    // =========================
                    const Text(
                      "Quick Actions",

                      style: LogisticsStyle.sectionTitle,
                    ),

                    const SizedBox(height: 12),

                    _actionCard(
                      Icons.qr_code_scanner,

                      "Scan Parcel",

                      '/parcel-scanner',
                    ),

                    _actionCard(
                      Icons.local_shipping_outlined,

                      "Manage Shipment",

                      '/shipment-management',
                    ),

                    _actionCard(
                      Icons.person_outline,

                      "Profile",

                      '/logistics-profile',
                    ),

                    const SizedBox(height: 20),
                  ],
                ),
              ),
            );
          },
        ),
      ),

      // =========================
      // BOTTOM NAVIGATION
      // =========================
      bottomNavigationBar: BottomNavigationBar(
        type: BottomNavigationBarType.fixed,

        currentIndex: selectedIndex,

        selectedItemColor: LogisticsStyle.primary,

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
            icon: Icon(Icons.inventory_2_outlined),

            label: "Shipments",
          ),

          BottomNavigationBarItem(
            icon: Icon(Icons.person_outline),

            label: "Profile",
          ),
        ],
      ),
    );
  }

  Widget _buildStat(String number, String title, IconData icon) {
    return Container(
      constraints: const BoxConstraints(minHeight: 90),

      padding: const EdgeInsets.all(12),

      decoration: LogisticsStyle.cardDecoration(),

      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,

        children: [
          Icon(icon, color: LogisticsStyle.primary, size: 22),

          const SizedBox(height: 6),

          Text(
            number,

            style: const TextStyle(
              fontSize: 20,

              fontWeight: FontWeight.w800,

              color: LogisticsStyle.primary,
            ),
          ),

          Text(title, style: LogisticsStyle.normalText),
        ],
      ),
    );
  }

  Widget _actionCard(IconData icon, String title, String route) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),

      decoration: LogisticsStyle.cardDecoration(),

      child: ListTile(
        dense: true,

        contentPadding: const EdgeInsets.symmetric(horizontal: 15, vertical: 2),

        onTap: () {
          Navigator.pushNamed(context, route);
        },

        leading: Icon(icon, color: LogisticsStyle.primary),

        title: Text(title, style: LogisticsStyle.cardTitle),

        trailing: const Icon(Icons.arrow_forward_ios, size: 14),
      ),
    );
  }
}
