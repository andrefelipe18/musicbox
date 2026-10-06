import 'package:musicbox/src/app/features/auth/domain/dtos/login_params.dart';
import 'package:musicbox/src/core/client_http/client_http.dart';
import 'package:musicbox/src/core/utils/endpoints.dart';

class AuthRemoteDatasource {
  final IRestClient _restClient;

  AuthRemoteDatasource({required IRestClient restClient}) : _restClient = restClient;

  Future<RestClientResponse> login(LoginParams params) => //
      _restClient.post(
        RestClientRequest(
          path: Endpoints.login,
          data: params.toJson(),
        ),
      );
}
