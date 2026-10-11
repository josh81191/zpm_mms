CREATE SCHEMA IF NOT EXISTS zpm_mms;

ALTER TABLE IF EXISTS public.roles SET SCHEMA zpm_mms;
ALTER TABLE IF EXISTS public.permissions SET SCHEMA zpm_mms;
ALTER TABLE IF EXISTS public.role_permissions SET SCHEMA zpm_mms;
ALTER TABLE IF EXISTS public.user_accounts SET SCHEMA zpm_mms;
ALTER TABLE IF EXISTS public.user_role_assignments SET SCHEMA zpm_mms;
ALTER TABLE IF EXISTS public.audit_logs SET SCHEMA zpm_mms;
