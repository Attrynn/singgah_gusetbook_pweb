/**
 * Object type registry mirroring the PHP FloorPlanObjectType enum.
 * Labels are Indonesian, matching the enum's label() method.
 */

export const OBJECT_TYPES = {
  // Seating
  chair: { label: 'Kursi', category: 'seating', isTable: false, isChair: true },
  round_table: { label: 'Meja Bulat', category: 'seating', isTable: true, isChair: false },
  rectangle_table: { label: 'Meja Persegi Panjang', category: 'seating', isTable: true, isChair: false },
  square_table: { label: 'Meja Persegi', category: 'seating', isTable: true, isChair: false },
  vip_table: { label: 'Meja VIP', category: 'seating', isTable: true, isChair: false },
  family_table: { label: 'Meja Keluarga', category: 'seating', isTable: true, isChair: false },
  custom_table: { label: 'Meja Kustom', category: 'seating', isTable: true, isChair: false },

  // Venue
  stage: { label: 'Panggung', category: 'venue', isTable: false, isChair: false },
  dance_floor: { label: 'Lantai Dansa', category: 'venue', isTable: false, isChair: false },
  entrance: { label: 'Pintu Masuk', category: 'venue', isTable: false, isChair: false },
  exit: { label: 'Pintu Keluar', category: 'venue', isTable: false, isChair: false },
  door: { label: 'Pintu', category: 'venue', isTable: false, isChair: false },
  wall: { label: 'Dinding', category: 'venue', isTable: false, isChair: false },
  pillar: { label: 'Pilar', category: 'venue', isTable: false, isChair: false },
  partition: { label: 'Partisi', category: 'venue', isTable: false, isChair: false },
  staircase: { label: 'Tangga', category: 'venue', isTable: false, isChair: false },
  aisle: { label: 'Lorong', category: 'venue', isTable: false, isChair: false },

  // Operational
  buffet_station: { label: 'Stasiun Buffet', category: 'operational', isTable: false, isChair: false },
  catering_station: { label: 'Stasiun Katering', category: 'operational', isTable: false, isChair: false },
  photo_booth: { label: 'Photo Booth', category: 'operational', isTable: false, isChair: false },
  guest_reception: { label: 'Resepsi Tamu', category: 'operational', isTable: false, isChair: false },
  registration_desk: { label: 'Meja Registrasi', category: 'operational', isTable: false, isChair: false },
  vendor_station: { label: 'Stasiun Vendor', category: 'operational', isTable: false, isChair: false },
  staff_station: { label: 'Stasiun Staf', category: 'operational', isTable: false, isChair: false },
  sound_area: { label: 'Area Sound', category: 'operational', isTable: false, isChair: false },
  lighting_area: { label: 'Area Lighting', category: 'operational', isTable: false, isChair: false },
  restricted_area: { label: 'Area Terbatas', category: 'operational', isTable: false, isChair: false },

  // General shapes
  rectangle: { label: 'Persegi Panjang', category: 'general', isTable: false, isChair: false },
  circle: { label: 'Lingkaran', category: 'general', isTable: false, isChair: false },
  ellipse: { label: 'Elips', category: 'general', isTable: false, isChair: false },
  line: { label: 'Garis', category: 'general', isTable: false, isChair: false },
  polygon: { label: 'Poligon', category: 'general', isTable: false, isChair: false },
  text_label: { label: 'Label Teks', category: 'general', isTable: false, isChair: false },
  custom_shape: { label: 'Bentuk Kustom', category: 'general', isTable: false, isChair: false },
}

export const ZONE_TYPES = {
  main_reception: { label: 'Resepsi Utama' },
  vip: { label: 'Area VIP' },
  family: { label: 'Area Keluarga' },
  guest: { label: 'Area Tamu' },
  stage: { label: 'Area Panggung' },
  dining: { label: 'Area Makan' },
  buffet: { label: 'Area Buffet' },
  photography: { label: 'Area Fotografi' },
  vendor: { label: 'Area Vendor' },
  restricted: { label: 'Area Terbatas' },
  entrance: { label: 'Area Masuk' },
  emergency_exit: { label: 'Jalur Evakuasi' },
  ceremony: { label: 'Area Upacara' },
  other: { label: 'Lainnya' },
}

export const CATEGORY_LABELS = {
  seating: 'Seating',
  venue: 'Venue',
  operational: 'Operasional',
  general: 'Bentuk',
}

export function objectLabel(type) {
  return OBJECT_TYPES[type]?.label ?? type
}

export function zoneLabel(type) {
  return ZONE_TYPES[type]?.label ?? type
}

export function isTableType(type) {
  return OBJECT_TYPES[type]?.isTable ?? false
}

export function isChairType(type) {
  return OBJECT_TYPES[type]?.isChair ?? false
}

export function objectCategory(type) {
  return OBJECT_TYPES[type]?.category ?? 'general'
}

/** Default dimensions (cm) for a newly created object of a given type. */
export function defaultSize(type) {
  switch (type) {
    case 'round_table':
    case 'square_table':
    case 'vip_table':
    case 'family_table':
    case 'custom_table':
      return { width: 180, height: 180 }
    case 'rectangle_table':
      return { width: 240, height: 100 }
    case 'chair':
      return { width: 45, height: 45 }
    case 'stage':
      return { width: 600, height: 300 }
    case 'dance_floor':
      return { width: 400, height: 400 }
    case 'wall':
      return { width: 400, height: 20 }
    case 'pillar':
      return { width: 60, height: 60 }
    case 'line':
      return { width: 300, height: 10 }
    case 'text_label':
      return { width: 200, height: 40 }
    default:
      return { width: 120, height: 120 }
  }
}

/** Default fill colour per category, used for new objects. */
export function defaultColor(type) {
  switch (objectCategory(type)) {
    case 'seating':
      return '#f5f5f5'
    case 'venue':
      return '#e8e2d6'
    case 'operational':
      return '#dfe8e4'
    default:
      return '#eceae4'
  }
}