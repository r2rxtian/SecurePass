-- Isolated test personas. Never add them to lrnph_users or lrn_master_list.
SET XACT_ABORT ON;

IF DB_NAME() <> N'LRNPH_OJT'
    THROW 50000, 'SecurePass Revamp migrations may run only in LRNPH_OJT.', 1;

IF OBJECT_ID(N'dbo.acdsecurepass_test_users', N'U') IS NOT NULL
    THROW 50001, 'SecurePass test users table already exists.', 1;

CREATE TABLE dbo.acdsecurepass_test_users (
    test_user_id INT IDENTITY(1,1) NOT NULL PRIMARY KEY,
    username NVARCHAR(80) NOT NULL,
    password_hash NVARCHAR(255) NOT NULL,
    bio_id NVARCHAR(50) NOT NULL,
    display_name NVARCHAR(200) NOT NULL,
    department NVARCHAR(200) NOT NULL,
    is_active BIT NOT NULL CONSTRAINT DF_acdsecurepass_test_users_active DEFAULT 1,
    created_at DATETIME2(0) NOT NULL CONSTRAINT DF_acdsecurepass_test_users_created DEFAULT SYSUTCDATETIME(),
    CONSTRAINT UQ_acdsecurepass_test_users_username UNIQUE (username),
    CONSTRAINT UQ_acdsecurepass_test_users_bio_id UNIQUE (bio_id),
    CONSTRAINT CK_acdsecurepass_test_users_name CHECK (LEFT(username, 5) = N'seed.'),
    CONSTRAINT CK_acdsecurepass_test_users_bio CHECK (LEFT(bio_id, 8) = N'TEST_SP_')
);
