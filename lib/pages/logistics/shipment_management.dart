import 'package:flutter/material.dart';

import '../../styles/logistics_style.dart';

class ShipmentManagementPage extends StatefulWidget {
  const ShipmentManagementPage({super.key});

  @override
  State<ShipmentManagementPage> createState() => _ShipmentManagementPageState();
}

class _ShipmentManagementPageState extends State<ShipmentManagementPage> {
  String selectedFilter = "All";

  final List<Map<String, String>> shipments = [
    {
      "order": "MS-10021",

      "customer": "Juan Dela Cruz",

      "rider": "Juan Rider",

      "status": "Out for Delivery",

      "payment": "COD",
    },

    {
      "order": "MS-10022",

      "customer": "Maria Santos",

      "rider": "Not Assigned",

      "status": "Processing",

      "payment": "COD",
    },

    {
      "order": "MS-10023",

      "customer": "Pedro Reyes",

      "rider": "Mark Rider",

      "status": "Delivered",

      "payment": "Paid",
    },
  ];

  final List<String> filters = [
    "All",

    "Processing",

    "Assigned",

    "Out for Delivery",

    "Delivered",
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: LogisticsStyle.background,

      appBar: AppBar(
        backgroundColor: Colors.white,

        elevation: 0,

        centerTitle: true,

        title: const Text(
          "Shipment Management",

          style: LogisticsStyle.appTitle,
        ),
      ),

      body: SafeArea(
        child: Column(
          children: [
            // =========================
            // FILTER
            // =========================
            SizedBox(
              height: 55,

              child: ListView.builder(
                scrollDirection: Axis.horizontal,

                padding: const EdgeInsets.symmetric(horizontal: 18),

                itemCount: filters.length,

                itemBuilder: (context, index) {
                  final filter = filters[index];

                  final selected = selectedFilter == filter;

                  return GestureDetector(
                    onTap: () {
                      setState(() {
                        selectedFilter = filter;
                      });
                    },

                    child: Container(
                      margin: const EdgeInsets.only(right: 10),

                      padding: const EdgeInsets.symmetric(
                        horizontal: 15,

                        vertical: 8,
                      ),

                      decoration: BoxDecoration(
                        color: selected ? LogisticsStyle.primary : Colors.white,

                        borderRadius: BorderRadius.circular(20),

                        border: Border.all(color: LogisticsStyle.border),
                      ),

                      child: Text(
                        filter,

                        style: TextStyle(
                          fontSize: 11,

                          fontWeight: FontWeight.w600,

                          color: selected
                              ? Colors.white
                              : LogisticsStyle.textGray,
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),

            const SizedBox(height: 10),

            Expanded(
              child: ListView.builder(
                padding: const EdgeInsets.all(18),

                itemCount: shipments.length,

                itemBuilder: (context, index) {
                  final shipment = shipments[index];

                  return _shipmentCard(shipment);
                },
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _shipmentCard(Map<String, String> shipment) {
    return Container(
      margin: const EdgeInsets.only(bottom: 15),

      padding: const EdgeInsets.all(15),

      decoration: LogisticsStyle.cardDecoration(),

      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,

        children: [
          // HEADER
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,

            children: [
              Text(shipment["order"]!, style: LogisticsStyle.cardTitle),

              _statusBadge(shipment["status"]!),
            ],
          ),

          const SizedBox(height: 15),

          _infoRow(Icons.person_outline, "Customer", shipment["customer"]!),

          const SizedBox(height: 10),

          _infoRow(Icons.delivery_dining, "Assigned Rider", shipment["rider"]!),

          const SizedBox(height: 10),

          _infoRow(Icons.payments_outlined, "Payment", shipment["payment"]!),

          const SizedBox(height: 15),

          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () {
                    _updateStatus(shipment);
                  },

                  child: const Text("Update"),
                ),
              ),

              const SizedBox(width: 10),

              Expanded(
                child: ElevatedButton(
                  style: LogisticsStyle.primaryButton(),

                  onPressed: () {
                    _viewShipment(shipment);
                  },

                  child: const Text("View"),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  void _updateStatus(Map<String, String> shipment) {
    setState(() {
      if (shipment["status"] == "Processing") {
        shipment["status"] = "Assigned";

        shipment["rider"] = "Juan Rider";
      } else if (shipment["status"] == "Assigned") {
        shipment["status"] = "Out for Delivery";
      } else if (shipment["status"] == "Out for Delivery") {
        shipment["status"] = "Delivered";
      }
    });
  }

  void _viewShipment(Map<String, String> shipment) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text("Viewing ${shipment["order"]}")));
  }

  Widget _statusBadge(String status) {
    Color color;

    switch (status) {
      case "Delivered":
        color = LogisticsStyle.success;

        break;

      case "Out for Delivery":
        color = LogisticsStyle.primary;

        break;

      case "Processing":
        color = LogisticsStyle.warning;

        break;

      default:
        color = LogisticsStyle.textGray;
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

  Widget _infoRow(IconData icon, String title, String value) {
    return Row(
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
    );
  }
}
