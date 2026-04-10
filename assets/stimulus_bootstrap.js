import { startStimulusApp } from '@symfony/stimulus-bundle';
import LoginController from './controllers/login_controller.js';
import RegisterController from './controllers/register_controller.js';
import PassportSyncController from './controllers/passport_sync_controller.js';
import ShareExportController from './controllers/share_export_controller.js';
import ClipboardController from './controllers/clipboard_controller.js';
import PlayHistoryController from './controllers/play_history_controller.js';
import './controllers/csrf_protection_controller.js';

const app = startStimulusApp();
app.register('login', LoginController);
app.register('register', RegisterController);
app.register('passport-sync', PassportSyncController);
app.register('share-export', ShareExportController);
app.register('clipboard', ClipboardController);
app.register('play-history', PlayHistoryController);
