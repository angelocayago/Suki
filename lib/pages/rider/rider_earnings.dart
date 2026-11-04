import 'package:flutter/material.dart';
import '../../styles/rider_style.dart';

class RiderEarningsPage extends StatelessWidget {
  const RiderEarningsPage({super.key});

  @override
  Widget build(BuildContext context) {
    Theme.of(context);
    return Scaffold(
      backgroundColor: RiderStyle.background,

      appBar: AppBar(
        backgroundColor: Theme.of(context).colorScheme.surface,

        elevation: 0,

        centerTitle: true,

        title: Text("Rider Earnings", style: RiderStyle.appTitle),
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
                    Text(
                      "Total Earnings Today",

                      style: RiderStyle.normalText,
                    ),

                    SizedBox(height: 8),

                    Text("₱850.00", style: RiderStyle.amount),

                    SizedBox(height: 8),

                    Text(
                      "12 completed deliveries",

                      style: RiderStyle.normalText,
                    ),
                  ],
                ),
              ),

              SizedBox(height: 22),

              // =========================
              // SUMMARY
              // =========================
              Text("Income Summary", style: RiderStyle.sectionTitle),

              SizedBox(height: 10),

              Row(
                children: [
                  _earningCard("₱850", "Today", Icons.today_outlined),

                  SizedBox(width: 10),

                  _earningCard(
                    "₱5,600",

                    "This Week",

                    Icons.calendar_view_week_outlined,
                  ),
                ],
              ),

              SizedBox(height: 12),

              Row(
                children: [
                  _earningCard(
                    "₱21,500",

                    "This Month",

                    Icons.calendar_month_outlined,
                  ),

                  SizedBox(width: 10),

                  _earningCard(
                    "125",

                    "Deliveries",

                    Icons.local_shipping_outlined,
                  ),
                ],
              ),

              SizedBox(height: 22),

              // =========================
              // DELIVERY HISTORY
              // =========================
              Text("Recent Deliveries", style: RiderStyle.sectionTitle),

              SizedBox(height: 10),

              _deliveryHistory(
                "MS-10021",

                "Juan Dela Cruz",

                "₱150",

                "Completed",
              ),

              _deliveryHistory("MS-10020", "Maria Santos", "₱120", "Completed"),

              _deliveryHistory("MS-10019", "Pedro Reyes", "₱200", "Completed"),

              SizedBox(height: 22),

              // =========================
              // PAYOUT
              // =========================
              Text("Withdrawal", style: RiderStyle.sectionTitle),

              SizedBox(height: 10),

              Container(
                padding: const EdgeInsets.all(16),

                decoration: RiderStyle.cardDecoration(),

                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,

                  children: [
                    Text(
                      "Available Balance",

                      style: RiderStyle.normalText,
                    ),

                    SizedBox(height: 5),

                    Text("₱8,450.00", style: RiderStyle.amount),

                    SizedBox(height: 15),

                    SizedBox(
                      width: double.infinity,

                      height: 45,

                      child: ElevatedButton(
                        style: RiderStyle.primaryButton(),

                        onPressed: () {
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(
                              content: Text("Payout request submitted."),
                            ),
                          );
                        },

                        child: Text("REQUEST PAYOUT"),
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

            Spacer(),

            Text(
              value,

              style: TextStyle(
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

              SizedBox(height: 4),

              Text(customer, style: RiderStyle.normalText),
            ],
          ),

          Column(
            crossAxisAlignment: CrossAxisAlignment.end,

            children: [
              Text(
                amount,

                style: TextStyle(
                  fontSize: 14,

                  fontWeight: FontWeight.w800,

                  color: RiderStyle.primary,
                ),
              ),

              Text(
                status,

                style: TextStyle(
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
