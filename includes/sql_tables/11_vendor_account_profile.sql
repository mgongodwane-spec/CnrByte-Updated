-- CnrByte Step 16: vendor business profile/settings alignment.
-- Run after 01_create_tables.sql, 08_step13_vendor_org_hr_schema.sql,
-- 09_step14_authentication_hardening.sql and 10_client_account_profile_messaging.sql.
USE `cnrbyte`;

-- Profile fields used by the vendor public profile renderer/settings page.
SET @profile_columns = 'about_para_text,vision_para_text,mission_para_text,values_para_text,about_img,vision_img,mission_img,values_img';
SET @has_about_text=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='profiles' AND COLUMN_NAME='about_para_text');
SET @sql_about_text=IF(@has_about_text=0,'ALTER TABLE profiles ADD COLUMN about_para_text TEXT NULL','SELECT 1'); PREPARE s_about_text FROM @sql_about_text; EXECUTE s_about_text; DEALLOCATE PREPARE s_about_text;
SET @has_vision_text=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='profiles' AND COLUMN_NAME='vision_para_text');
SET @sql_vision_text=IF(@has_vision_text=0,'ALTER TABLE profiles ADD COLUMN vision_para_text TEXT NULL','SELECT 1'); PREPARE s_vision_text FROM @sql_vision_text; EXECUTE s_vision_text; DEALLOCATE PREPARE s_vision_text;
SET @has_mission_text=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='profiles' AND COLUMN_NAME='mission_para_text');
SET @sql_mission_text=IF(@has_mission_text=0,'ALTER TABLE profiles ADD COLUMN mission_para_text TEXT NULL','SELECT 1'); PREPARE s_mission_text FROM @sql_mission_text; EXECUTE s_mission_text; DEALLOCATE PREPARE s_mission_text;
SET @has_values_text=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='profiles' AND COLUMN_NAME='values_para_text');
SET @sql_values_text=IF(@has_values_text=0,'ALTER TABLE profiles ADD COLUMN values_para_text TEXT NULL','SELECT 1'); PREPARE s_values_text FROM @sql_values_text; EXECUTE s_values_text; DEALLOCATE PREPARE s_values_text;
SET @has_about_img=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='profiles' AND COLUMN_NAME='about_img');
SET @sql_about_img=IF(@has_about_img=0,'ALTER TABLE profiles ADD COLUMN about_img VARCHAR(255) NULL','SELECT 1'); PREPARE s_about_img FROM @sql_about_img; EXECUTE s_about_img; DEALLOCATE PREPARE s_about_img;
SET @has_vision_img=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='profiles' AND COLUMN_NAME='vision_img');
SET @sql_vision_img=IF(@has_vision_img=0,'ALTER TABLE profiles ADD COLUMN vision_img VARCHAR(255) NULL','SELECT 1'); PREPARE s_vision_img FROM @sql_vision_img; EXECUTE s_vision_img; DEALLOCATE PREPARE s_vision_img;
SET @has_mission_img=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='profiles' AND COLUMN_NAME='mission_img');
SET @sql_mission_img=IF(@has_mission_img=0,'ALTER TABLE profiles ADD COLUMN mission_img VARCHAR(255) NULL','SELECT 1'); PREPARE s_mission_img FROM @sql_mission_img; EXECUTE s_mission_img; DEALLOCATE PREPARE s_mission_img;
SET @has_values_img=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='profiles' AND COLUMN_NAME='values_img');
SET @sql_values_img=IF(@has_values_img=0,'ALTER TABLE profiles ADD COLUMN values_img VARCHAR(255) NULL','SELECT 1'); PREPARE s_values_img FROM @sql_values_img; EXECUTE s_values_img; DEALLOCATE PREPARE s_values_img;
SET @vendor_profile_index=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='profiles' AND INDEX_NAME='idx_profiles_vendor_latest');
SET @sql_vendor_profile_index=IF(@vendor_profile_index=0,'CREATE INDEX idx_profiles_vendor_latest ON profiles (vendor_id,id)','SELECT 1'); PREPARE s_vendor_profile_index FROM @sql_vendor_profile_index; EXECUTE s_vendor_profile_index; DEALLOCATE PREPARE s_vendor_profile_index;
SET @vendor_contacts_index=(SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contacts' AND INDEX_NAME='idx_contacts_vendor_name');
SET @sql_vendor_contacts_index=IF(@vendor_contacts_index=0,'CREATE INDEX idx_contacts_vendor_name ON contacts (vendor_id,contact_name)','SELECT 1'); PREPARE s_vendor_contacts_index FROM @sql_vendor_contacts_index; EXECUTE s_vendor_contacts_index; DEALLOCATE PREPARE s_vendor_contacts_index;
