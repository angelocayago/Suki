import 'package:flutter/material.dart';

import '../../styles/logistics_style.dart';
import '../../widgets/theme_toggle_button.dart';

class LogisticsHomePage extends StatefulWidget {
  const LogisticsHomePage({super.key});

  @override
  State<LogisticsHomePage> createState() => _LogisticsHomePageState();
}

class _LogisticsHomePageState extends State<LogisticsHomePage> {
  int selectedIndex = 0;

  @override
  Widget build(BuildContext context) {
    Theme.of(context);
    return Scaffold(
      resizeToAvoidBottomInset: true,

      backgroundColor: LogisticsStyle.background,

      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            return SingleChildScrollView(
              physics: BouncingScrollPhysics(),

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
                            Text(
                              "Hello Logistics 👋",

                              style: LogisticsStyle.greeting,
                            ),

                            SizedBox(height: 4),

                            Text(
                              "Shipment Dashboard",

                              style: LogisticsStyle.appTitle,
                            ),
                          ],
                        ),

                        Row(
                          children: [
                            const ThemeToggleButton(),
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
                      ],
                    ),

                    SizedBox(height: 22),

                    // =========================
                    // SUMMARY
                    // =========================
                    Text(
                      "Shipment Summary",

                      style: LogisticsStyle.sectionTitle,
                    ),

                    SizedBox(height: 12),

                    Row(
                      children: [
                        Expanded(
                          child: _buildStat(
                            "25",

                            "Pending",

                            Icons.pending_actions,
                          ),
                        ),

                        SizedBox(width: 10),

                        Expanded(
                          child: _buildStat(
                            "18",

                            "Processing",

                            Icons.inventory_outlined,
                          ),
                        ),
                      ],
                    ),

                    SizedBox(height: 12),

                    Row(
                      children: [
                        Expanded(
                          child: _buildStat(
                            "42",

                            "Delivered",

                            Icons.check_circle_outline,
                          ),
                        ),

                        SizedBox(width: 10),

                        Expanded(
                          child: _buildStat(
                            "5",

                            "Riders",

                            Icons.delivery_dining,
                          ),
                        ),
                      ],
                    ),

                    SizedBox(height: 22),

                    // =========================
                    // QUICK ACTIONS
                    // =========================
                    Text(
                      "Quick Actions",

                      style: LogisticsStyle.sectionTitle,
                    ),

                    SizedBox(height: 12),

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

                    SizedBox(height: 20),
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
      constraints: BoxConstraints(minHeight: 90),

      padding: const EdgeInsets.all(12),

      decoration: LogisticsStyle.cardDecoration(),

      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,

        children: [
          Icon(icon, color: LogisticsStyle.primary, size: 22),

          SizedBox(height: 6),

          Text(
            number,

            style: TextStyle(
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

        trailing: Icon(Icons.arrow_forward_ios, size: 14),
      ),
    );
  }
}
