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
      SnackBar(content: Text("Parcel successfully scanned.")),
    );
  }

  void confirmPickup() {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text("Parcel pickup confirmed.")));

    Navigator.pushNamed(context, '/delivery-details');
  }

  @override
  Widget build(BuildContext context) {
    Theme.of(context);
    return Scaffold(
      backgroundColor: RiderStyle.background,

      appBar: AppBar(
        backgroundColor: Theme.of(context).colorScheme.surface,

        elevation: 0,

        centerTitle: true,

        title: Text("Scan Parcel", style: RiderStyle.appTitle),
      ),

      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(18),

          child: Column(
            crossAxisAlignment: CrossAxisAlignment.center,

            children: [
              SizedBox(height: 25),

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

                      SizedBox(height: 15),

                      Text(
                        "Align QR / Barcode\ninside the frame",

                        textAlign: TextAlign.center,

                        style: TextStyle(color: Colors.white, fontSize: 14),
                      ),
                    ],
                  ),
                ),
              ),

              SizedBox(height: 25),

              // =========================
              // SCAN BUTTON
              // =========================
              SizedBox(
                width: double.infinity,

                height: 48,

                child: ElevatedButton(
                  style: RiderStyle.primaryButton(),

                  onPressed: scanParcel,

                  child: Text("SCAN PARCEL"),
                ),
              ),

              SizedBox(height: 25),

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
                      Text(
                        "Parcel Information Found",

                        style: RiderStyle.sectionTitle,
                      ),

                      SizedBox(height: 15),

                      _infoRow(
                        Icons.qr_code,

                        "Tracking Number",

                        trackingNumber,
                      ),

                      SizedBox(height: 10),

                      _infoRow(
                        Icons.inventory_2_outlined,

                        "Status",

                        "Ready for Pickup",
                      ),

                      SizedBox(height: 10),

                      _infoRow(
                        Icons.location_on_outlined,

                        "Destination",

                        "Brgy. San Isidro",
                      ),

                      SizedBox(height: 18),

                      SizedBox(
                        width: double.infinity,

                        height: 42,

                        child: ElevatedButton(
                          style: RiderStyle.primaryButton(),

                          onPressed: confirmPickup,

                          child: Text("CONFIRM PICKUP"),
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

        SizedBox(width: 10),

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
