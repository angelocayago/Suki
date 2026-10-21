import 'package:flutter/material.dart';
import '../../styles/rider_style.dart';

class RiderProfilePage extends StatelessWidget {
  const RiderProfilePage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: RiderStyle.background,

      appBar: AppBar(
        backgroundColor: Colors.white,

        elevation: 0,

        centerTitle: true,

        title: const Text("Rider Profile", style: RiderStyle.appTitle),
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

                      backgroundColor: RiderStyle.primary,

                      child: const Icon(
                        Icons.person,

                        size: 50,

                        color: Colors.white,
                      ),
                    ),

                    const SizedBox(height: 12),

                    const Text(
                      "Juan Dela Cruz",

                      style: TextStyle(
                        fontSize: 18,

                        fontWeight: FontWeight.w800,

                        color: RiderStyle.textDark,
                      ),
                    ),

                    const SizedBox(height: 5),

                    const Text(
                      "Rider ID: RDR-0001",

                      style: RiderStyle.normalText,
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 25),

              // =========================
              // PERSONAL INFORMATION
              // =========================
              const Text(
                "Personal Information",

                style: RiderStyle.sectionTitle,
              ),

              const SizedBox(height: 10),

              _profileCard([
                _info(Icons.phone_outlined, "Phone Number", "0912-345-6789"),

                _info(Icons.email_outlined, "Email", "rider@test.com"),

                _info(
                  Icons.location_on_outlined,

                  "Address",

                  "Brgy. San Isidro",
                ),
              ]),

              const SizedBox(height: 22),

              // =========================
              // VEHICLE INFORMATION
              // =========================
              const Text("Vehicle Information", style: RiderStyle.sectionTitle),

              const SizedBox(height: 10),

              _profileCard([
                _info(Icons.motorcycle_outlined, "Vehicle Type", "Motorcycle"),

                _info(
                  Icons.confirmation_number_outlined,

                  "Plate Number",

                  "ABC-1234",
                ),

                _info(Icons.badge_outlined, "License Status", "Verified"),
              ]),

              const SizedBox(height: 22),

              // =========================
              // VERIFICATION
              // =========================
              const Text(
                "Account Verification",

                style: RiderStyle.sectionTitle,
              ),

              const SizedBox(height: 10),

              _verificationTile("Driver License", "Approved", true),

              _verificationTile("Vehicle Registration", "Approved", true),

              _verificationTile("Rider Documents", "Pending Review", false),

              const SizedBox(height: 25),

              // =========================
              // SETTINGS
              // =========================
              const Text("Settings", style: RiderStyle.sectionTitle),

              const SizedBox(height: 10),

              _settingsButton(
                Icons.notifications_outlined,

                "Notifications",

                () {},
              ),

              _settingsButton(Icons.lock_outline, "Change Password", () {}),

              _settingsButton(Icons.help_outline, "Help Center", () {}),

              const SizedBox(height: 25),

              // =========================
              // LOGOUT
              // =========================
              SizedBox(
                width: double.infinity,

                height: 45,

                child: OutlinedButton(
                  style: OutlinedButton.styleFrom(
                    foregroundColor: RiderStyle.danger,

                    side: const BorderSide(color: RiderStyle.danger),

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

      decoration: RiderStyle.cardDecoration(),

      child: Column(children: children),
    );
  }

  Widget _info(IconData icon, String title, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),

      child: Row(
        children: [
          Icon(icon, size: 18, color: RiderStyle.primary),

          const SizedBox(width: 10),

          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,

              children: [
                Text(title, style: RiderStyle.normalText),

                Text(value, style: RiderStyle.cardTitle),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _verificationTile(String title, String status, bool approved) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),

      padding: const EdgeInsets.all(14),

      decoration: RiderStyle.cardDecoration(),

      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,

        children: [
          Text(title, style: RiderStyle.cardTitle),

          Row(
            children: [
              Icon(
                approved ? Icons.check_circle : Icons.pending,

                size: 18,

                color: approved ? RiderStyle.success : RiderStyle.warning,
              ),

              const SizedBox(width: 5),

              Text(
                status,

                style: TextStyle(
                  fontSize: 11,

                  fontWeight: FontWeight.w700,

                  color: approved ? RiderStyle.success : RiderStyle.warning,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _settingsButton(IconData icon, String title, VoidCallback action) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),

      decoration: RiderStyle.cardDecoration(),

      child: ListTile(
        onTap: action,

        leading: Icon(icon, color: RiderStyle.primary),

        title: Text(title, style: RiderStyle.cardTitle),

        trailing: const Icon(Icons.arrow_forward_ios, size: 15),
      ),
    );
  }
}
