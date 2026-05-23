import 'package:flutter_test/flutter_test.dart';

import 'package:mobile_dashboard/main.dart';

void main() {
  testWidgets('Shows login screen on first load', (WidgetTester tester) async {
    await tester.pumpWidget(const VendorPulseMobileApp());

    expect(find.text('VendorPulse Login'), findsOneWidget);
    expect(find.text('Sign in'), findsOneWidget);
  });
}
