import 'package:flutter/material.dart';
import '../../styles/rider_style.dart';

class ParcelInformationPage extends StatelessWidget {
  const ParcelInformationPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: RiderStyle.background,

      appBar: AppBar(
        backgroundColor: Colors.white,

        elevation: 0,

        centerTitle: true,

        title: const Text("Parcel Information", style: RiderStyle.appTitle),
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

                decoration: RiderStyle.cardDecoration(),

                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    const Text("Tracking Number", style: RiderStyle.normalText),

                    const SizedBox(height: 5),

                    const Text("MS-10021", style: RiderStyle.amount),

                    const SizedBox(height: 12),

                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,

                        vertical: 7,
                      ),

                      decoration: BoxDecoration(
                        color: const Color(0xFFEFFAF5),

                        borderRadius: BorderRadius.circular(20),
                      ),

                      child: const Text(
                        "Ready for Delivery",

                        style: TextStyle(
                          fontSize: 11,

                          fontWeight: FontWeight.w700,

                          color: RiderStyle.success,
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 20),

              // =========================
              // CUSTOMER DETAILS
              // =========================
              const Text("Customer Details", style: RiderStyle.sectionTitle),

              const SizedBox(height: 10),

              _informationCard([
                _item(Icons.person_outline, "Customer Name", "Juan Dela Cruz"),

                _item(Icons.phone_outlined, "Contact Number", "0912-345-6789"),

                _item(
                  Icons.location_on_outlined,

                  "Delivery Address",

                  "Brgy. San Isidro",
                ),
              ]),

              const SizedBox(height: 20),

              // =========================
              // PARCEL DETAILS
              // =========================
              const Text("Parcel Details", style: RiderStyle.sectionTitle),

              const SizedBox(height: 10),

              _informationCard([
                _item(Icons.inventory_2_outlined, "Package", "Coffee Products"),

                _item(Icons.scale_outlined, "Weight", "2.5 kg"),

                _item(
                  Icons.payments_outlined,

                  "Payment Method",

                  "Cash on Delivery",
                ),

                _item(Icons.money_outlined, "Amount", "₱850.00"),
              ]),

              const SizedBox(height: 20),

              // =========================
              // DELIVERY TIMELINE
              // =========================
              const Text("Delivery Status", style: RiderStyle.sectionTitle),

              const SizedBox(height: 10),

              _statusTimeline(),

              const SizedBox(height: 25),

              // =========================
              // BUTTON
              // =========================
              SizedBox(
                width: double.infinity,

                height: 48,

                child: ElevatedButton(
                  style: RiderStyle.primaryButton(),

                  onPressed: () {
                    Navigator.pushNamed(context, '/delivery-details');
                  },

                  child: const Text("START DELIVERY"),
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

      decoration: RiderStyle.cardDecoration(),

      child: Column(children: children),
    );
  }

  Widget _item(IconData icon, String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),

      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,

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
      ),
    );
  }

  Widget _statusTimeline() {
    return Container(
      padding: const EdgeInsets.all(15),

      decoration: RiderStyle.cardDecoration(),

      child: Column(
        children: [
          _timelineItem("Parcel Accepted", true),

          _timelineItem("Parcel Picked Up", true),

          _timelineItem("Out for Delivery", false),

          _timelineItem("Delivered", false),
        ],
      ),
    );
  }

  Widget _timelineItem(String title, bool completed) {
    return Row(
      children: [
        Icon(
          completed ? Icons.check_circle : Icons.radio_button_unchecked,

          size: 20,

          color: completed ? RiderStyle.success : RiderStyle.textGray,
        ),

        const SizedBox(width: 10),

        Text(
          title,

          style: TextStyle(
            fontSize: 12,

            fontWeight: FontWeight.w600,

            color: completed ? RiderStyle.textDark : RiderStyle.textGray,
          ),
        ),
      ],
    );
  }
}
