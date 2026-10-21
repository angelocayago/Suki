import 'package:flutter/material.dart';
import '../../styles/rider_style.dart';

class RiderEarningsPage extends StatelessWidget {
  const RiderEarningsPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: RiderStyle.background,

      appBar: AppBar(
        backgroundColor: Colors.white,

        elevation: 0,

        centerTitle: true,

        title: const Text("Rider Earnings", style: RiderStyle.appTitle),
      ),

      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(18),

          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              // =========================
              // TOTAL EARNINGS CARD
              // =========================
              Container(
                width: double.infinity,

                padding: const EdgeInsets.all(20),

                decoration: RiderStyle.statusCard(),

                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    const Text(
                      "Total Earnings Today",

                      style: RiderStyle.normalText,
                    ),

                    const SizedBox(height: 8),

                    const Text("₱850.00", style: RiderStyle.amount),

                    const SizedBox(height: 8),

                    const Text(
                      "12 completed deliveries",

                      style: RiderStyle.normalText,
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 22),

              // =========================
              // SUMMARY
              // =========================
              const Text("Income Summary", style: RiderStyle.sectionTitle),

              const SizedBox(height: 10),

              Row(
                children: [
                  _earningCard("₱850", "Today", Icons.today_outlined),

                  const SizedBox(width: 10),

                  _earningCard(
                    "₱5,600",

                    "This Week",

                    Icons.calendar_view_week_outlined,
                  ),
                ],
              ),

              const SizedBox(height: 12),

              Row(
                children: [
                  _earningCard(
                    "₱21,500",

                    "This Month",

                    Icons.calendar_month_outlined,
                  ),

                  const SizedBox(width: 10),

                  _earningCard(
                    "125",

                    "Deliveries",

                    Icons.local_shipping_outlined,
                  ),
                ],
              ),

              const SizedBox(height: 22),

              // =========================
              // DELIVERY HISTORY
              // =========================
              const Text("Recent Deliveries", style: RiderStyle.sectionTitle),

              const SizedBox(height: 10),

              _deliveryHistory(
                "MS-10021",

                "Juan Dela Cruz",

                "₱150",

                "Completed",
              ),

              _deliveryHistory("MS-10020", "Maria Santos", "₱120", "Completed"),

              _deliveryHistory("MS-10019", "Pedro Reyes", "₱200", "Completed"),

              const SizedBox(height: 22),

              // =========================
              // PAYOUT
              // =========================
              const Text("Withdrawal", style: RiderStyle.sectionTitle),

              const SizedBox(height: 10),

              Container(
                padding: const EdgeInsets.all(16),

                decoration: RiderStyle.cardDecoration(),

                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    const Text(
                      "Available Balance",

                      style: RiderStyle.normalText,
                    ),

                    const SizedBox(height: 5),

                    const Text("₱8,450.00", style: RiderStyle.amount),

                    const SizedBox(height: 15),

                    SizedBox(
                      width: double.infinity,

                      height: 45,

                      child: ElevatedButton(
                        style: RiderStyle.primaryButton(),

                        onPressed: () {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(
                              content: Text("Payout request submitted."),
                            ),
                          );
                        },

                        child: const Text("REQUEST PAYOUT"),
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

  Widget _earningCard(String value, String label, IconData icon) {
    return Expanded(
      child: Container(
        height: 110,

        padding: const EdgeInsets.all(12),

        decoration: RiderStyle.cardDecoration(),

        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,

          children: [
            Icon(icon, size: 22, color: RiderStyle.primary),

            const Spacer(),

            Text(
              value,

              style: const TextStyle(
                fontSize: 16,

                fontWeight: FontWeight.w800,

                color: RiderStyle.primary,
              ),
            ),

            Text(label, style: RiderStyle.normalText),
          ],
        ),
      ),
    );
  }

  Widget _deliveryHistory(
    String order,

    String customer,

    String amount,

    String status,
  ) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),

      padding: const EdgeInsets.all(14),

      decoration: RiderStyle.cardDecoration(),

      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,

        children: [
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,

            children: [
              Text(order, style: RiderStyle.cardTitle),

              const SizedBox(height: 4),

              Text(customer, style: RiderStyle.normalText),
            ],
          ),

          Column(
            crossAxisAlignment: CrossAxisAlignment.end,

            children: [
              Text(
                amount,

                style: const TextStyle(
                  fontSize: 14,

                  fontWeight: FontWeight.w800,

                  color: RiderStyle.primary,
                ),
              ),

              Text(
                status,

                style: const TextStyle(
                  fontSize: 10,

                  color: RiderStyle.success,

                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
