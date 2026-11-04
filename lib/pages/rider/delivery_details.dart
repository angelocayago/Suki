import 'package:flutter/material.dart';
import '../../styles/rider_style.dart';

class DeliveryDetailsPage extends StatefulWidget {
  const DeliveryDetailsPage({super.key});

  @override
  State<DeliveryDetailsPage> createState() => _DeliveryDetailsPageState();
}

class _DeliveryDetailsPageState extends State<DeliveryDetailsPage> {
  String deliveryStatus = "On the Way";

  bool delivered = false;

  void markDelivered() {
    setState(() {
      deliveryStatus = "Delivered";

      delivered = true;
    });

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text("Delivery completed successfully.")),
    );
  }

  void openNavigation() {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text("Opening navigation...")));

    // Future:
    // Google Maps integration here
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

        title: Text("Delivery Details", style: RiderStyle.appTitle),
      ),

      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(18),

          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              // =========================
              // DELIVERY STATUS
              // =========================
              Container(
                width: double.infinity,

                padding: const EdgeInsets.all(16),

                decoration: RiderStyle.statusCard(),

                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,

                  children: [
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.start,

                      children: [
                        Text(
                          "Delivery Status",

                          style: RiderStyle.normalText,
                        ),

                        SizedBox(height: 5),

                        Text(
                          deliveryStatus,

                          style: TextStyle(
                            fontSize: 16,

                            fontWeight: FontWeight.w800,

                            color: delivered
                                ? RiderStyle.success
                                : RiderStyle.primary,
                          ),
                        ),
                      ],
                    ),

                    Icon(
                      delivered ? Icons.check_circle : Icons.delivery_dining,

                      size: 35,

                      color: RiderStyle.primary,
                    ),
                  ],
                ),
              ),

              SizedBox(height: 20),

              // =========================
              // ROUTE INFORMATION
              // =========================
              Text("Delivery Route", style: RiderStyle.sectionTitle),

              SizedBox(height: 10),

              Container(
                padding: const EdgeInsets.all(15),

                decoration: RiderStyle.cardDecoration(),

                child: Column(
                  children: [
                    _locationItem(
                      Icons.store_outlined,

                      "Pickup",

                      "Mobile Suki Warehouse",
                    ),

                    SizedBox(height: 18),

                    Divider(),

                    SizedBox(height: 18),

                    _locationItem(
                      Icons.location_on_outlined,

                      "Destination",

                      "Brgy. San Isidro",
                    ),
                  ],
                ),
              ),

              SizedBox(height: 20),

              // =========================
              // CUSTOMER INFORMATION
              // =========================
              Text(
                "Customer Information",

                style: RiderStyle.sectionTitle,
              ),

              SizedBox(height: 10),

              Container(
                padding: const EdgeInsets.all(15),

                decoration: RiderStyle.cardDecoration(),

                child: Column(
                  children: [
                    _infoRow(
                      Icons.person_outline,

                      "Customer",

                      "Juan Dela Cruz",
                    ),

                    SizedBox(height: 12),

                    _infoRow(Icons.phone_outlined, "Contact", "0912-345-6789"),

                    SizedBox(height: 12),

                    _infoRow(
                      Icons.payments_outlined,

                      "Payment",

                      "Cash on Delivery - ₱850",
                    ),
                  ],
                ),
              ),

              SizedBox(height: 20),

              // =========================
              // MAP PLACEHOLDER
              // =========================
              Text("Navigation", style: RiderStyle.sectionTitle),

              SizedBox(height: 10),

              Container(
                height: 160,

                width: double.infinity,

                decoration: BoxDecoration(
                  color: RiderStyle.border,

                  borderRadius: BorderRadius.circular(15),
                ),

                child: Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,

                    children: [
                      Icon(
                        Icons.map_outlined,
                        size: 45,
                        color: RiderStyle.textGray,
                      ),

                      SizedBox(height: 8),

                      Text(
                        "Map Navigation",

                        style: TextStyle(color: RiderStyle.textGray),
                      ),
                    ],
                  ),
                ),
              ),

              SizedBox(height: 12),

              SizedBox(
                width: double.infinity,

                height: 45,

                child: ElevatedButton(
                  style: RiderStyle.primaryButton(),

                  onPressed: openNavigation,

                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,

                    children: [
                      Icon(Icons.navigation_outlined, size: 18),

                      SizedBox(width: 8),

                      Text("OPEN NAVIGATION"),
                    ],
                  ),
                ),
              ),

              SizedBox(height: 20),

              // =========================
              // DELIVERY PROOF
              // =========================
              Text(
                "Delivery Confirmation",

                style: RiderStyle.sectionTitle,
              ),

              SizedBox(height: 10),

              Container(
                padding: const EdgeInsets.all(15),

                decoration: RiderStyle.cardDecoration(),

                child: Column(
                  children: [
                    Icon(
                      Icons.camera_alt_outlined,

                      size: 45,

                      color: RiderStyle.primary,
                    ),

                    SizedBox(height: 10),

                    Text(
                      "Upload proof of delivery",

                      style: RiderStyle.cardTitle,
                    ),

                    SizedBox(height: 10),

                    OutlinedButton(
                      onPressed: () {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(
                            content: Text("Camera will open here."),
                          ),
                        );
                      },

                      child: Text("TAKE PHOTO"),
                    ),
                  ],
                ),
              ),

              SizedBox(height: 22),

              // =========================
              // COMPLETE DELIVERY
              // =========================
              SizedBox(
                width: double.infinity,

                height: 48,

                child: ElevatedButton(
                  style: RiderStyle.primaryButton(),

                  onPressed: delivered ? null : markDelivered,

                  child: Text(
                    delivered ? "DELIVERY COMPLETED" : "MARK AS DELIVERED",
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _locationItem(IconData icon, String title, String value) {
    return Row(
      children: [
        Icon(icon, color: RiderStyle.primary, size: 22),

        SizedBox(width: 12),

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
    );
  }

  Widget _infoRow(IconData icon, String title, String value) {
    return Row(
      children: [
        Icon(icon, size: 18, color: RiderStyle.primary),

        SizedBox(width: 10),

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
    );
  }
}
