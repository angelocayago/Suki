import 'package:flutter/material.dart';

import '../../styles/logistics_style.dart';

class LogisticsProfilePage extends StatelessWidget {
  const LogisticsProfilePage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: LogisticsStyle.background,

      appBar: AppBar(
        backgroundColor: Colors.white,

        elevation: 0,

        centerTitle: true,

        title: const Text("Logistics Profile", style: LogisticsStyle.appTitle),
      ),

      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(18),

          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              // =========================
              // PROFILE HEADER
              // =========================
              Center(
                child: Column(
                  children: [
                    CircleAvatar(
                      radius: 45,

                      backgroundColor: LogisticsStyle.primary,

                      child: const Icon(
                        Icons.business,

                        size: 45,

                        color: Colors.white,
                      ),
                    ),

                    const SizedBox(height: 12),

                    const Text(
                      "Mobile Suki Logistics",

                      style: TextStyle(
                        fontSize: 18,

                        fontWeight: FontWeight.w800,

                        color: LogisticsStyle.textDark,
                      ),
                    ),

                    const SizedBox(height: 5),

                    const Text(
                      "Logistics Staff ID: LOG-0001",

                      style: LogisticsStyle.normalText,
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 25),

              // =========================
              // STAFF INFORMATION
              // =========================
              const Text(
                "Staff Information",

                style: LogisticsStyle.sectionTitle,
              ),

              const SizedBox(height: 10),

              _profileCard([
                _infoRow(Icons.person_outline, "Staff Name", "Admin User"),

                _infoRow(Icons.email_outlined, "Email", "logistics@test.com"),

                _infoRow(Icons.phone_outlined, "Contact", "0912-345-6789"),
              ]),

              const SizedBox(height: 22),

              // =========================
              // WAREHOUSE INFORMATION
              // =========================
              const Text(
                "Warehouse Information",

                style: LogisticsStyle.sectionTitle,
              ),

              const SizedBox(height: 10),

              _profileCard([
                _infoRow(
                  Icons.warehouse_outlined,

                  "Warehouse",

                  "Mobile Suki Main Hub",
                ),

                _infoRow(Icons.location_on_outlined, "Location", "Lucena City"),

                _infoRow(
                  Icons.inventory_2_outlined,

                  "Managed Parcels",

                  "250 shipments",
                ),
              ]),

              const SizedBox(height: 22),

              // =========================
              // ACCOUNT STATUS
              // =========================
              const Text("Account Status", style: LogisticsStyle.sectionTitle),

              const SizedBox(height: 10),

              _statusCard("Account Verification", "Verified", true),

              _statusCard("Warehouse Access", "Approved", true),

              _statusCard("System Permission", "Administrator", true),

              const SizedBox(height: 22),

              // =========================
              // SETTINGS
              // =========================
              const Text("Settings", style: LogisticsStyle.sectionTitle),

              const SizedBox(height: 10),

              _settingsTile(
                Icons.notifications_outlined,

                "Notifications",

                () {},
              ),

              _settingsTile(Icons.lock_outline, "Change Password", () {}),

              _settingsTile(Icons.help_outline, "Help Center", () {}),

              const SizedBox(height: 25),

              // =========================
              // LOGOUT
              // =========================
              SizedBox(
                width: double.infinity,

                height: 45,

                child: OutlinedButton(
                  style: OutlinedButton.styleFrom(
                    foregroundColor: LogisticsStyle.danger,

                    side: const BorderSide(color: LogisticsStyle.danger),

                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),

                  onPressed: () {
                    Navigator.pushReplacementNamed(context, '/login');
                  },

                  child: const Text("LOGOUT"),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _profileCard(List<Widget> children) {
    return Container(
      width: double.infinity,

      padding: const EdgeInsets.all(15),

      decoration: LogisticsStyle.cardDecoration(),

      child: Column(children: children),
    );
  }

  Widget _infoRow(IconData icon, String title, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),

      child: Row(
        children: [
          Icon(icon, size: 18, color: LogisticsStyle.primary),

          const SizedBox(width: 10),

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                Text(title, style: LogisticsStyle.normalText),

                Text(value, style: LogisticsStyle.cardTitle),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _statusCard(String title, String status, bool verified) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),

      padding: const EdgeInsets.all(14),

      decoration: LogisticsStyle.cardDecoration(),

      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,

        children: [
          Text(title, style: LogisticsStyle.cardTitle),

          Row(
            children: [
              Icon(
                verified ? Icons.check_circle : Icons.pending,

                size: 18,

                color: verified
                    ? LogisticsStyle.success
                    : LogisticsStyle.warning,
              ),

              const SizedBox(width: 5),

              Text(
                status,

                style: TextStyle(
                  fontSize: 11,

                  fontWeight: FontWeight.w700,

                  color: verified
                      ? LogisticsStyle.success
                      : LogisticsStyle.warning,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _settingsTile(IconData icon, String title, VoidCallback action) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),

      decoration: LogisticsStyle.cardDecoration(),

      child: ListTile(
        onTap: action,

        leading: Icon(icon, color: LogisticsStyle.primary),

        title: Text(title, style: LogisticsStyle.cardTitle),

        trailing: const Icon(Icons.arrow_forward_ios, size: 15),
      ),
    );
  }
}
