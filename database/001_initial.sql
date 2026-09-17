-- Run only against the dedicated SecurePass test database.
SET XACT_ABORT ON;

IF DB_NAME() <> N'LRNPH_OJT'
    THROW 50000, 'SecurePass Revamp migrations may run only in LRNPH_OJT.', 1;

BEGIN TRY
    BEGIN TRANSACTION;

    IF OBJECT_ID(N'dbo.acdsecurepass_requests', N'U') IS NOT NULL
        OR OBJECT_ID(N'dbo.acdsecurepass_items', N'U') IS NOT NULL
        OR OBJECT_ID(N'dbo.acdsecurepass_attachments', N'U') IS NOT NULL
        OR OBJECT_ID(N'dbo.acdsecurepass_approval_steps', N'U') IS NOT NULL
        OR OBJECT_ID(N'dbo.acdsecurepass_user_roles', N'U') IS NOT NULL
        OR OBJECT_ID(N'dbo.acdsecurepass_audit_events', N'U') IS NOT NULL
        THROW 50001, 'One or more SecurePass Revamp tables already exist.', 1;

    CREATE TABLE dbo.acdsecurepass_requests (
        request_id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        reference_number VARCHAR(24) NULL,
        creator_bio_id NVARCHAR(50) NOT NULL,
        creator_name NVARCHAR(200) NOT NULL,
        department NVARCHAR(200) NOT NULL,
        department_head_bio_id NVARCHAR(50) NULL,
        company NVARCHAR(200) NOT NULL,
        category NVARCHAR(100) NOT NULL,
        other_category NVARCHAR(200) NULL,
        release_date DATE NOT NULL,
        remarks NVARCHAR(MAX) NULL,
        status VARCHAR(20) NOT NULL CONSTRAINT DF_acdsecurepass_requests_status DEFAULT 'draft',
        current_stage VARCHAR(40) NULL,
        revision INT NOT NULL CONSTRAINT DF_acdsecurepass_requests_revision DEFAULT 1,
        created_at DATETIME2(0) NOT NULL CONSTRAINT DF_acdsecurepass_requests_created DEFAULT SYSUTCDATETIME(),
        updated_at DATETIME2(0) NOT NULL CONSTRAINT DF_acdsecurepass_requests_updated DEFAULT SYSUTCDATETIME(),
        row_version ROWVERSION NOT NULL,
        CONSTRAINT CK_acdsecurepass_requests_status CHECK (status IN ('draft', 'pending', 'approved', 'rejected', 'cancelled')),
        CONSTRAINT CK_acdsecurepass_requests_revision CHECK (revision > 0)
    );

    CREATE UNIQUE INDEX UX_acdsecurepass_requests_reference
        ON dbo.acdsecurepass_requests(reference_number)
        WHERE reference_number IS NOT NULL;
    CREATE INDEX IX_acdsecurepass_requests_creator
        ON dbo.acdsecurepass_requests(creator_bio_id, created_at DESC);
    CREATE INDEX IX_acdsecurepass_requests_queue
        ON dbo.acdsecurepass_requests(status, current_stage, created_at DESC);

    CREATE TABLE dbo.acdsecurepass_items (
        item_id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        request_id BIGINT NOT NULL,
        sort_order INT NOT NULL,
        item_name NVARCHAR(300) NOT NULL,
        quantity DECIMAL(18,3) NOT NULL,
        unit_of_measure NVARCHAR(50) NOT NULL,
        unit_price DECIMAL(19,4) NULL,
        expected_return_date DATE NULL,
        CONSTRAINT FK_acdsecurepass_items_request FOREIGN KEY (request_id)
            REFERENCES dbo.acdsecurepass_requests(request_id),
        CONSTRAINT UQ_acdsecurepass_items_order UNIQUE (request_id, sort_order),
        CONSTRAINT CK_acdsecurepass_items_quantity CHECK (quantity > 0),
        CONSTRAINT CK_acdsecurepass_items_price CHECK (unit_price IS NULL OR unit_price >= 0)
    );

    CREATE TABLE dbo.acdsecurepass_attachments (
        attachment_id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        item_id BIGINT NOT NULL,
        storage_key UNIQUEIDENTIFIER NOT NULL CONSTRAINT DF_acdsecurepass_attachments_key DEFAULT NEWID(),
        original_filename NVARCHAR(255) NOT NULL,
        mime_type VARCHAR(120) NOT NULL,
        size_bytes BIGINT NOT NULL,
        uploaded_by_bio_id NVARCHAR(50) NOT NULL,
        uploaded_at DATETIME2(0) NOT NULL CONSTRAINT DF_acdsecurepass_attachments_uploaded DEFAULT SYSUTCDATETIME(),
        CONSTRAINT FK_acdsecurepass_attachments_item FOREIGN KEY (item_id)
            REFERENCES dbo.acdsecurepass_items(item_id),
        CONSTRAINT UQ_acdsecurepass_attachments_key UNIQUE (storage_key),
        CONSTRAINT CK_acdsecurepass_attachments_size CHECK (size_bytes > 0)
    );
    CREATE INDEX IX_acdsecurepass_attachments_item
        ON dbo.acdsecurepass_attachments(item_id);

    CREATE TABLE dbo.acdsecurepass_approval_steps (
        step_id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        request_id BIGINT NOT NULL,
        revision INT NOT NULL,
        step_order INT NOT NULL,
        stage VARCHAR(40) NOT NULL,
        assigned_role VARCHAR(80) NULL,
        assigned_bio_id NVARCHAR(50) NULL,
        decision VARCHAR(20) NOT NULL CONSTRAINT DF_acdsecurepass_steps_decision DEFAULT 'pending',
        actor_bio_id NVARCHAR(50) NULL,
        remarks NVARCHAR(2000) NULL,
        acted_at DATETIME2(0) NULL,
        created_at DATETIME2(0) NOT NULL CONSTRAINT DF_acdsecurepass_steps_created DEFAULT SYSUTCDATETIME(),
        CONSTRAINT FK_acdsecurepass_steps_request FOREIGN KEY (request_id)
            REFERENCES dbo.acdsecurepass_requests(request_id),
        CONSTRAINT UQ_acdsecurepass_steps_order UNIQUE (request_id, revision, step_order),
        CONSTRAINT CK_acdsecurepass_steps_decision CHECK (decision IN ('pending', 'approved', 'rejected', 'skipped')),
        CONSTRAINT CK_acdsecurepass_steps_assignment CHECK (assigned_role IS NOT NULL OR assigned_bio_id IS NOT NULL)
    );
    CREATE INDEX IX_acdsecurepass_steps_queue
        ON dbo.acdsecurepass_approval_steps(decision, assigned_role, assigned_bio_id);

    CREATE TABLE dbo.acdsecurepass_user_roles (
        bio_id NVARCHAR(50) NOT NULL,
        role_name VARCHAR(80) NOT NULL,
        is_active BIT NOT NULL CONSTRAINT DF_acdsecurepass_roles_active DEFAULT 1,
        assigned_at DATETIME2(0) NOT NULL CONSTRAINT DF_acdsecurepass_roles_assigned DEFAULT SYSUTCDATETIME(),
        CONSTRAINT PK_acdsecurepass_user_roles PRIMARY KEY (bio_id, role_name)
    );

    CREATE TABLE dbo.acdsecurepass_audit_events (
        event_id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY,
        request_id BIGINT NULL,
        actor_bio_id NVARCHAR(50) NULL,
        action_name VARCHAR(80) NOT NULL,
        previous_status VARCHAR(20) NULL,
        new_status VARCHAR(20) NULL,
        previous_stage VARCHAR(40) NULL,
        new_stage VARCHAR(40) NULL,
        details_json NVARCHAR(MAX) NULL,
        occurred_at DATETIME2(0) NOT NULL CONSTRAINT DF_acdsecurepass_audit_occurred DEFAULT SYSUTCDATETIME(),
        CONSTRAINT FK_acdsecurepass_audit_request FOREIGN KEY (request_id)
            REFERENCES dbo.acdsecurepass_requests(request_id),
        CONSTRAINT CK_acdsecurepass_audit_json CHECK (details_json IS NULL OR ISJSON(details_json) = 1)
    );
    CREATE INDEX IX_acdsecurepass_audit_request
        ON dbo.acdsecurepass_audit_events(request_id, occurred_at DESC);

    COMMIT TRANSACTION;
END TRY
BEGIN CATCH
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW;
END CATCH;
