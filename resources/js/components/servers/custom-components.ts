import { registerCustomComponent } from '@/components/servers/game-settings-renderer';
import ReforgerScenarioPicker from '@/components/servers/reforger-scenario-picker';

// Register all custom field components used by the schema-driven settings renderer.
// Import this module from any file that renders game settings to ensure components
// are registered before the renderer encounters them.
registerCustomComponent('scenario-picker', ReforgerScenarioPicker);
