import 'package:flutter/material.dart';
import '../../styles/rider_style.dart';

class RiderScannerPage extends StatefulWidget {
  const RiderScannerPage({super.key});

  @override
  State<RiderScannerPage> createState() => _RiderScannerPageState();
}

class _RiderScannerPageState extends State<RiderScannerPage> {
  bool isScanned = false;

  String trackingNumber = "MS-10021";

  void scanParcel() {
    setState(() {
      isScanned = true;
    });

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text("Parcel successfully scanned.")),
    );
  }

  void confirmPickup() {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(const SnackBar(content: Text("Parcel pickup confirmed.")));

    Navigator.pushNamed(context, '/delivery-details');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: RiderStyle.background,

      appBar: AppBar(
        backgroundColor: Colors.white,

        elevation: 0,

        centerTitle: true,

        title: const Text("Scan Parcel", style: RiderStyle.appTitle),
      ),

      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(18),

          child: Column(
            crossAxisAlignment: CrossAxisAlignment.center,

            children: [
              const SizedBox(height: 25),

              // =========================
              // SCANNER BOX
              // =========================
              Container(
                width: double.infinity,

                height: 280,

                decoration: BoxDecoration(
                  color: Colors.black,

                  borderRadius: BorderRadius.circular(18),
                ),

                child: Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,

                    children: [
                      Icon(
                        Icons.qr_code_scanner,

                        size: 90,

                        color: Colors.white,
                      ),

                      const SizedBox(height: 15),

                      const Text(
                        "Align QR / Barcode\ninside the frame",

                        textAlign: TextAlign.center,

                        style: TextStyle(color: Colors.white, fontSize: 14),
                      ),
                    ],
                  ),
                ),
              ),

              const SizedBox(height: 25),

              // =========================
              // SCAN BUTTON
              // =========================
              SizedBox(
                width: double.infinity,

                height: 48,

                child: ElevatedButton(
                  style: RiderStyle.primaryButton(),

                  onPressed: scanParcel,

                  child: const Text("SCAN PARCEL"),
                ),
              ),

              const SizedBox(height: 25),

              // =========================
              // RESULT
              // =========================
              if (isScanned)
                Container(
                  width: double.infinity,

                  padding: const EdgeInsets.all(16),

                  decoration: RiderStyle.cardDecoration(),

                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,

                    children: [
                      const Text(
                        "Parcel Information Found",

                        style: RiderStyle.sectionTitle,
                      ),

                      const SizedBox(height: 15),

                      _infoRow(
                        Icons.qr_code,

                        "Tracking Number",

                        trackingNumber,
                      ),

                      const SizedBox(height: 10),

                      _infoRow(
                        Icons.inventory_2_outlined,

                        "Status",

                        "Ready for Pickup",
                      ),

                      const SizedBox(height: 10),

                      _infoRow(
                        Icons.location_on_outlined,

                        "Destination",

                        "Brgy. San Isidro",
                      ),

                      const SizedBox(height: 18),

                      SizedBox(
                        width: double.infinity,

                        height: 42,

                        child: ElevatedButton(
                          style: RiderStyle.primaryButton(),

                          onPressed: confirmPickup,

                          child: const Text("CONFIRM PICKUP"),
                        ),
                      ),
                    ],
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _infoRow(IconData icon, String label, String value) {
    return Row(
      children: [
        Icon(icon, size: 18, color: RiderStyle.primary),

        const SizedBox(width: 10),

        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              Text(label, style: RiderStyle.normalText),

              Text(value, style: RiderStyle.cardTitle),
            ],
          ),
        ),
      ],
    );
  }
}
