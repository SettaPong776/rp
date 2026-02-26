-- สร้างตาราง departments
CREATE TABLE IF NOT EXISTS `departments` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `sort_order` INT(11) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ข้อมูลเริ่มต้น (13 แผนก/ฝ่าย)
INSERT IGNORE INTO `departments` (`name`, `sort_order`) VALUES
('สำนักส่งเสริมวิชาการและงานทะเบียน', 1),
('สถาบันวิจัยและพัฒนา', 2),
('สำนักศิลปะและวัฒนธรรม', 3),
('สำนักวิทยบริการและเทคโนโลยีสารสนเทศ', 4),
('สำนักงานอธิการบดี กองกลาง', 5),
('สำนักงานอธิการบดี กองนโยบายและแผน', 6),
('สำนักงานอธิการบดี กองพัฒนานักศึกษา', 7),
('คณะครุศาสตร์', 8),
('คณะมนุษยศาสตร์และสังคมศาสตร์', 9),
('คณะวิทยาการจัดการ', 10),
('คณะวิทยาศาสตร์และเทคโนโลยี', 11),
('คณะเทคโนโลยีอุตสาหกรรม', 12),
('โรงเรียนสาธิตมหาวิทยาลัยราชภัฏเลย', 13);
