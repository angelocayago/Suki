import 'buyer_home_api.dart';

class BuyerHomeRepository {
  BuyerHomeRepository._();

  static Future<BuyerHomeData> getHomeData() {
    return BuyerHomeApi.getHomeData();
  }
}
