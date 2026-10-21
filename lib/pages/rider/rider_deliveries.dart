import 'package:flutter/material.dart';
import '../../styles/rider_style.dart';

class RiderDeliveriesPage extends StatefulWidget {
  const RiderDeliveriesPage({super.key});

  @override
  State<RiderDeliveriesPage> createState() => _RiderDeliveriesPageState();
}

class _RiderDeliveriesPageState extends State<RiderDeliveriesPage> {
  final List<Map<String, String>> deliveries = [
    {
      "order": "MS-10021",
      "customer": "Juan Dela Cruz",
      "pickup": "Mobile Suki Warehouse",
      "destination": "Brgy. San Isidro",
      "payment": "₱250",
      "status": "Available",
    },

    {
      "order": "MS-10022",
      "customer": "Maria Santos",
      "pickup": "Mobile Suki Warehouse",
      "destination": "Brgy. San Pedro",
      "payment": "₱180",
      "status": "Available",
    },

    {
      "order": "MS-10023",
      "customer": "Pedro Reyes",
      "pickup": "Partner Store",
      "destination": "Brgy. Sta. Cruz",
      "payment": "₱320",
      "status": "Available",
    },
  ];

  void acceptDelivery(int index) {
    setState(() {
      deliveries[index]["status"] = "Accepted";
    });

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text("Delivery ${deliveries[index]["order"]} accepted"),
      ),
    );
  }

  void rejectDelivery(int index) {
    setState(() {
      deliveries[index]["status"] = "Rejected";
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: RiderStyle.background,

      appBar: AppBar(
        backgroundColor: Colors.white,

        elevation: 0,

        title: const Text("My Deliveries", style: RiderStyle.appTitle),

        centerTitle: true,
      ),

      body: SafeArea(
        child: ListView.builder(
          padding: const EdgeInsets.all(18),

          itemCount: deliveries.length,

          itemBuilder: (context, index) {
            final delivery = deliveries[index];

            return Container(
              margin: const EdgeInsets.only(bottom: 15),

              padding: const EdgeInsets.all(15),

              decoration: RiderStyle.cardDecoration(),

              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,

                children: [
                  // ORDER HEADER
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,

                    children: [
                      Text(
                        "Order #${delivery["order"]}",

                        style: RiderStyle.cardTitle,
                      ),

                      _statusBadge(delivery["status"]!),
                    ],
                  ),

                  const SizedBox(height: 15),

                  _deliveryInfo(
                    Icons.person_outline,

                    "Customer",

                    delivery["customer"]!,
                  ),

                  const SizedBox(height: 10),

                  _deliveryInfo(
                    Icons.store_outlined,

                    "Pickup",

                    delivery["pickup"]!,
                  ),

                  const SizedBox(height: 10),

                  _deliveryInfo(
                    Icons.location_on_outlined,

                    "Destination",

                    delivery["destination"]!,
                  ),

                  const SizedBox(height: 10),

                  _deliveryInfo(
                    Icons.payments_outlined,

                    "Payment",

                    delivery["payment"]!,
                  ),

                  const SizedBox(height: 18),

                  // BUTTONS
                  if (delivery["status"] == "Available")
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton(
                            style: OutlinedButton.styleFrom(
                              foregroundColor: RiderStyle.danger,

                              side: const BorderSide(color: RiderStyle.danger),

                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(10),
                              ),
                            ),

                            onPressed: () {
                              rejectDelivery(index);
                            },

                            child: const Text("Reject"),
                          ),
                        ),

                        const SizedBox(width: 10),

                        Expanded(
                          child: ElevatedButton(
                            style: RiderStyle.primaryButton(),

                            onPressed: () {
                              acceptDelivery(index);
                            },

                            child: const Text("Accept"),
                          ),
                        ),
                      ],
                    )
                  else if (delivery["status"] == "Accepted")
                    SizedBox(
                      width: double.infinity,

                      height: 42,

                      child: ElevatedButton(
                        style: RiderStyle.primaryButton(),

                        onPressed: () {
                          Navigator.pushNamed(
                            context,

                            '/rider-parcel-information',

                            arguments: delivery,
                          );
                        },

                        child: const Text("View Parcel"),
                      ),
                    ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }

  Widget _deliveryInfo(IconData icon, String title, String value) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,

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
    );
  }

  Widget _statusBadge(String status) {
    Color color;

    switch (status) {
      case "Accepted":
        color = RiderStyle.success;

        break;

      case "Rejected":
        color = RiderStyle.danger;

        break;

      default:
        color = RiderStyle.warning;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),

      decoration: BoxDecoration(
        color: color.withOpacity(.15),

        borderRadius: BorderRadius.circular(20),
      ),

      child: Text(
        status,

        style: TextStyle(
          fontSize: 10,

          fontWeight: FontWeight.w700,

          color: color,
        ),
      ),
    );
  }
}
