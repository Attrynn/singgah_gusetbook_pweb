/**
 * Floor plan editor entry.
 * Registers the `floorPlanEditor` Alpine component before Alpine boots.
 * Loaded only on the editor page; Konva is bundled through Vite (no CDN).
 */

import Alpine from 'alpinejs'
import floorPlanEditor from './editor/index.js'

// Register once. Alpine.data is safe to call before Alpine.start().
Alpine.data('floorPlanEditor', (config) => floorPlanEditor(config))

export default floorPlanEditor