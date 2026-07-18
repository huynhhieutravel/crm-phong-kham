-- Migration for Patient Personal Notes
-- Run this on Production (VPS) database

ALTER TABLE patients ADD COLUMN personal_notes TEXT AFTER notes;
