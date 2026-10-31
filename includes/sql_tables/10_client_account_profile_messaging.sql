-- CnrByte Step 15: client profile/contact settings and direct-message support.
-- Run after 01_create_tables.sql, 08_step13_vendor_org_hr_schema.sql and 09_step14_authentication_hardening.sql.
USE `cnrbyte`;

-- Address fields are already defined on profiles in the base schema. This migration
-- confirms that the account/profile feature uses the existing clients + profiles model.
-- Add a supporting lookup index only when it is not already present.
SET @profile_client_index_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='profiles' AND INDEX_NAME='idx_profiles_client_latest'
);
SET @profile_client_index_sql = IF(
  @profile_client_index_exists=0,
  'CREATE INDEX idx_profiles_client_latest ON profiles (client_id, id)',
  'SELECT 1'
);
PREPARE stmt_profile_client_index FROM @profile_client_index_sql;
EXECUTE stmt_profile_client_index;
DEALLOCATE PREPARE stmt_profile_client_index;

-- Supporting index for saved direct contacts, if absent.
SET @contact_owner_index_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contacts' AND INDEX_NAME='idx_contacts_client_contact'
);
SET @contact_owner_index_sql = IF(
  @contact_owner_index_exists=0,
  'CREATE INDEX idx_contacts_client_contact ON contacts (client_id, contact_id)',
  'SELECT 1'
);
PREPARE stmt_contact_owner_index FROM @contact_owner_index_sql;
EXECUTE stmt_contact_owner_index;
DEALLOCATE PREPARE stmt_contact_owner_index;

-- Supporting direct-message conversation lookup index, if absent.
SET @direct_message_index_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='messages' AND INDEX_NAME='idx_direct_message_pair_date'
);
SET @direct_message_index_sql = IF(
  @direct_message_index_exists=0,
  'CREATE INDEX idx_direct_message_pair_date ON messages (sender_id, receiver_id, created_at)',
  'SELECT 1'
);
PREPARE stmt_direct_message_index FROM @direct_message_index_sql;
EXECUTE stmt_direct_message_index;
DEALLOCATE PREPARE stmt_direct_message_index;
