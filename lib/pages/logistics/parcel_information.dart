import 'package:flutter/material.dart';

import '../../styles/logistics_style.dart';

class ParcelInformationPage extends StatefulWidget {
  const ParcelInformationPage({super.key});

  @override
  State<ParcelInformationPage> createState() => _ParcelInformationPageState();
}

class _ParcelInformationPageState extends State<ParcelInformationPage> {
  String selectedRider = "No Rider Assigned";

  bool assigned = false;

  void assignRider() {
    setState(() {
      selectedRider = "Juan Dela Cruz (Rider)";

      assigned = true;
    });

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text("Rider assigned successfully.")),
    );
  }

  @override
  Widget build(BuildContext context) {
    Theme.of(context);
    return Scaffold(
      backgroundColor: LogisticsStyle.background,

      appBar: AppBar(
        backgroundColor: Theme.of(context).colorScheme.surface,

        elevation: 0,

        centerTitle: true,

        title: Text("Parcel Information", style: LogisticsStyle.appTitle),
      ),

      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(18),

          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              // =========================
              // TRACKING CARD
              // =========================
              Container(
                width: double.infinity,

                padding: const EdgeInsets.all(18),

                decoration: LogisticsStyle.cardDecoration(),

                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    Text(
                      "Tracking Number",

                      style: LogisticsStyle.normalText,
                    ),

                    SizedBox(height: 5),

                    Text(
                      "MS-10021",

                      style: TextStyle(
                        fontSize: 24,

                        fontWeight: FontWeight.w800,

                        color: LogisticsStyle.primary,
                      ),
                    ),

                    SizedBox(height: 12),

                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,

                        vertical: 6,
                      ),

                      decoration: BoxDecoration(
                        color: LogisticsStyle.successBackground,

                        borderRadius: BorderRadius.circular(20),
                      ),

                      child: Text(
                        "Arrived at Logistics",

                        style: TextStyle(
                          fontSize: 11,

                          fontWeight: FontWeight.w700,

                          color: LogisticsStyle.success,
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              SizedBox(height: 22),

              // =========================
              // CUSTOMER DETAILS
              // =========================
              Text(
                "Customer Information",

                style: LogisticsStyle.sectionTitle,
              ),

              SizedBox(height: 10),

              _informationCard([
                _infoRow(Icons.person_outline, "Customer", "Juan Dela Cruz"),

                _infoRow(Icons.phone_outlined, "Contact", "0912-345-6789"),

                _infoRow(
                  Icons.location_on_outlined,

                  "Address",

                  "Brgy. San Isidro",
                ),
              ]),

              SizedBox(height: 22),

              // =========================
              // PARCEL DETAILS
              // =========================
              Text(
                "Shipment Details",

                style: LogisticsStyle.sectionTitle,
              ),

              SizedBox(height: 10),

              _informationCard([
                _infoRow(
                  Icons.inventory_2_outlined,

                  "Package",

                  "Coffee Products",
                ),

                _infoRow(Icons.scale_outlined, "Weight", "2.5 kg"),

                _infoRow(
                  Icons.payments_outlined,

                  "Payment",

                  "Cash on Delivery",
                ),

                _infoRow(Icons.money_outlined, "Amount", "₱850.00"),
              ]),

              SizedBox(height: 22),

              // =========================
              // RIDER ASSIGNMENT
              // =========================
              Text(
                "Rider Assignment",

                style: LogisticsStyle.sectionTitle,
              ),

              SizedBox(height: 10),

              Container(
                padding: const EdgeInsets.all(15),

                decoration: LogisticsStyle.cardDecoration(),

                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    Row(
                      children: [
                        Icon(
                          Icons.delivery_dining,

                          color: LogisticsStyle.primary,
                        ),

                        SizedBox(width: 10),

                        Expanded(
                          child: Text(
                            selectedRider,

                            style: LogisticsStyle.cardTitle,
                          ),
                        ),
                      ],
                    ),

                    SizedBox(height: 15),

                    SizedBox(
                      width: double.infinity,

                      height: 45,

                      child: ElevatedButton(
                        style: LogisticsStyle.primaryButton(),

                        onPressed: assigned ? null : assignRider,

                        child: Text(
                          assigned ? "RIDER ASSIGNED" : "ASSIGN RIDER",
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              SizedBox(height: 22),

              // =========================
              // SHIPMENT TIMELINE
              // =========================
              Text(
                "Shipment History",

                style: LogisticsStyle.sectionTitle,
              ),

              SizedBox(height: 10),

              Container(
                padding: const EdgeInsets.all(15),

                decoration: LogisticsStyle.cardDecoration(),

                child: Column(
                  children: [
                    _timeline("Order Created", true),

                    _timeline("Parcel Received", true),

                    _timeline("Rider Assigned", assigned),

                    _timeline("Out for Delivery", false),

                    _timeline("Delivered", false),
                  ],
                ),
              ),

              SizedBox(height: 20),

              SizedBox(
                width: double.infinity,

                height: 45,

                child: ElevatedButton(
                  style: LogisticsStyle.primaryButton(),

                  onPressed: () {
                    Navigator.pushNamed(context, '/shipment-management');
                  },

                  child: Text("MANAGE SHIPMENT"),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _informationCard(List<Widget> children) {
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

          SizedBox(width: 10),

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

  Widget _timeline(String title, bool completed) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),

      child: Row(
        children: [
          Icon(
            completed ? Icons.check_circle : Icons.radio_button_unchecked,

            color: completed ? LogisticsStyle.success : LogisticsStyle.textGray,
          ),

          SizedBox(width: 10),

          Text(
            title,

            style: TextStyle(
              fontSize: 12,

              fontWeight: FontWeight.w600,

              color: completed
                  ? LogisticsStyle.textDark
                  : LogisticsStyle.textGray,
            ),
          ),
        ],
      ),
    );
  }
}
