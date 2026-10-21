import 'package:flutter/material.dart';

import '../../styles/logistics_style.dart';

class ParcelScannerPage extends StatefulWidget {
  const ParcelScannerPage({super.key});

  @override
  State<ParcelScannerPage> createState() => _ParcelScannerPageState();
}

class _ParcelScannerPageState extends State<ParcelScannerPage> {
  bool isScanned = false;

  String trackingNumber = "MS-10021";

  void scanParcel() {
    setState(() {
      isScanned = true;
    });

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text("Parcel verified successfully.")),
    );
  }

  void confirmReceived() {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text("Parcel received and recorded.")),
    );

    Navigator.pushNamed(context, '/logistics-parcel-information');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: LogisticsStyle.background,

      appBar: AppBar(
        backgroundColor: Colors.white,

        elevation: 0,

        centerTitle: true,

        title: const Text("Parcel Scanner", style: LogisticsStyle.appTitle),
      ),

      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(18),

          child: Column(
            children: [
              const SizedBox(height: 20),

              // =========================
              // SCANNER AREA
              // =========================
              Container(
                height: 280,

                width: double.infinity,

                decoration: BoxDecoration(
                  color: Colors.black,

                  borderRadius: BorderRadius.circular(18),
                ),

                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,

                  children: [
                    const Icon(
                      Icons.qr_code_scanner,

                      size: 90,

                      color: Colors.white,
                    ),

                    const SizedBox(height: 15),

                    const Text(
                      "Scan Parcel QR / Barcode",

                      style: TextStyle(color: Colors.white, fontSize: 14),
                    ),

                    const SizedBox(height: 5),

                    const Text(
                      "Verify incoming shipment",

                      style: TextStyle(color: Colors.white70, fontSize: 11),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 20),

              // =========================
              // SCAN BUTTON
              // =========================
              SizedBox(
                width: double.infinity,

                height: 48,

                child: ElevatedButton(
                  style: LogisticsStyle.primaryButton(),

                  onPressed: scanParcel,

                  child: const Text("SCAN PARCEL"),
                ),
              ),

              const SizedBox(height: 20),

              // =========================
              // RESULT
              // =========================
              if (isScanned)
                Container(
                  width: double.infinity,

                  padding: const EdgeInsets.all(16),

                  decoration: LogisticsStyle.cardDecoration(),

                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,

                    children: [
                      const Text(
                        "Shipment Found",

                        style: LogisticsStyle.sectionTitle,
                      ),

                      const SizedBox(height: 15),

                      _infoRow(
                        Icons.qr_code,

                        "Tracking Number",

                        trackingNumber,
                      ),

                      const SizedBox(height: 12),

                      _infoRow(
                        Icons.inventory_2_outlined,

                        "Status",

                        "Arrived at Logistics",
                      ),

                      const SizedBox(height: 12),

                      _infoRow(
                        Icons.location_on_outlined,

                        "Destination",

                        "Customer Area",
                      ),

                      const SizedBox(height: 18),

                      SizedBox(
                        width: double.infinity,

                        height: 44,

                        child: ElevatedButton(
                          style: LogisticsStyle.primaryButton(),

                          onPressed: confirmReceived,

                          child: const Text("CONFIRM RECEIVED"),
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
        Icon(icon, size: 18, color: LogisticsStyle.primary),

        const SizedBox(width: 10),

        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              Text(label, style: LogisticsStyle.normalText),

              Text(value, style: LogisticsStyle.cardTitle),
            ],
          ),
        ),
      ],
    );
  }
}
