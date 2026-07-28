whatsapp-integration/

│
├── app/
│
│   ├── Controllers/
│   │      HealthController.php
│   │      MessageController.php
│   │      WebhookController.php
│   │
│   ├── Services/
│   │      MetaApiService.php
│   │      MessageService.php
│   │      WebhookService.php
│   │
│   ├── Repositories/
│   │      MessageRepository.php
│   │      EventRepository.php
│   │
│   ├── Helpers/
│   │      Logger.php
│   │      Response.php
│   │      Validator.php
│   │
│   ├── Database/
│   │      Database.php
│   │
│   └── Core/
│          Router.php
│          Request.php
│          Controller.php
│
├── config/
│      app.php
│      database.php
│      whatsapp.php
│
├── public/
│      index.php
│      webhook.php
│      assets/
│
├── routes/
│      api.php
│      web.php
│
├── storage/
│      logs/
│
├── sql/
│      schema.sql
│
├── vendor/
│
├── .env
│
├── composer.json
│
└── README.md