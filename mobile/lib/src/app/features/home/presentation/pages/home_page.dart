import 'package:musicbox/routes.dart';
import 'package:musicbox/src/core/DI/dependency_injector.dart';
import 'package:musicbox/src/core/services/session_service.dart';
import 'package:flutter/material.dart';

class HomePage extends StatelessWidget {
  const HomePage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Home'),
        centerTitle: true,
        actions: [
          IconButton(
            onPressed: () async {
              final sessionService = injector<SessionService>();
              await sessionService.removeToken();
              router.go('/login');
            },
            icon: const Icon(Icons.logout_sharp),
          ),
        ],
      ),
      body: const Center(
        child: Text('Home Page'),
      ),
    );
  }
}
