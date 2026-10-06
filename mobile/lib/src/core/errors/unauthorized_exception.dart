import 'package:musicbox/src/core/errors/base_exception.dart';

class UnauthorizedException extends BaseException {
  UnauthorizedException({
    required super.message,
  });
}
