import 'package:musicbox/src/core/errors/errors.dart';

class ServerException extends BaseException {
  final String error;

  ServerException({
    required super.message,
    required this.error,
    super.stackTracing,
  });
}
