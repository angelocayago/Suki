import 'buyer_home_api.dart';

// =========================================================
// SUKISHOP MOBILE - BUYER HOME REPOSITORY
// =========================================================
//
// Handles communication between BuyerHomePage
// and BuyerHomeApi.
//
// Data Flow:
// BuyerHomePage
//       ↓
// BuyerHomeRepository
//       ↓
// BuyerHomeApi
//       ↓
// ApiServices
//       ↓
// Laravel Backend
//
// =========================================================

class BuyerHomeRepository {
  BuyerHomeRepository._();

  // =======================================================
  // FETCH BUYER HOMEPAGE DATA
  // =======================================================

  static Future<BuyerHomeData> getHomeData() {
    return BuyerHomeApi.getHomeData();
  }
}
