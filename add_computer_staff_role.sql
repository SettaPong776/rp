ALTER TABLE users MODIFY COLUMN role ENUM(
    'admin', 'user',
    'building_staff', 'electrical_staff', 'plumbing_staff', 'ac_staff',
    'head_building', 'head_electrical', 'head_plumbing', 'head_ac',
    'computer_staff'
) NOT NULL DEFAULT 'user';
