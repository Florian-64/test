-- ============================================================
-- Event Management Web App — MySQL Schema
-- ============================================================


-- ------------------------------------------------------------
-- Categories & Tags
-- ------------------------------------------------------------
CREATE TABLE categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE tags (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL UNIQUE,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Users
-- ------------------------------------------------------------
CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    username    VARCHAR(60)  NOT NULL UNIQUE,
    email       VARCHAR(255) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('visitor','user','organizer','admin') NOT NULL DEFAULT 'user',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Events
-- ------------------------------------------------------------
CREATE TABLE events (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    owner_id        INT           NOT NULL,
    category_id     INT           NULL,
    title           VARCHAR(255)  NOT NULL,
    description     TEXT          NOT NULL,
    location        VARCHAR(255)  NOT NULL,
    event_date      DATETIME      NOT NULL,
    status          ENUM('draft','published','cancelled','completed') NOT NULL DEFAULT 'draft',
    max_participants INT          NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id)    REFERENCES users(id)      ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE event_tags (
    event_id INT NOT NULL,
    tag_id   INT NOT NULL,
    PRIMARY KEY (event_id, tag_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id)   REFERENCES tags(id)   ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Status History (audit trail for event status transitions)
-- ------------------------------------------------------------
CREATE TABLE status_history (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    event_id    INT          NOT NULL,
    old_status  VARCHAR(20)  NOT NULL,
    new_status  VARCHAR(20)  NOT NULL,
    changed_by  INT          NOT NULL,
    changed_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id)  REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Invitations & RSVPs
-- ------------------------------------------------------------
CREATE TABLE invitations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    event_id    INT NOT NULL,
    sender_id   INT NOT NULL,
    recipient_id INT NOT NULL,
    status      ENUM('pending','accepted','declined','maybe') NOT NULL DEFAULT 'pending',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_invite (event_id, recipient_id),
    FOREIGN KEY (event_id)     REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id)    REFERENCES users(id)  ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE rsvps (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    event_id    INT NOT NULL,
    user_id     INT NOT NULL,
    status      ENUM('going','not_going','maybe') NOT NULL DEFAULT 'going',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_rsvp (event_id, user_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Polls & Votes
-- ------------------------------------------------------------
CREATE TABLE polls (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    event_id    INT          NOT NULL,
    question    VARCHAR(500) NOT NULL,
    created_by  INT          NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id)  REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE poll_options (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    poll_id     INT          NOT NULL,
    option_text VARCHAR(255) NOT NULL,
    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE votes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    poll_id     INT NOT NULL,
    option_id   INT NOT NULL,
    user_id     INT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_vote (poll_id, user_id),
    FOREIGN KEY (poll_id)   REFERENCES polls(id)        ON DELETE CASCADE,
    FOREIGN KEY (option_id) REFERENCES poll_options(id)  ON DELETE CASCADE,
    FOREIGN KEY (user_id)   REFERENCES users(id)         ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Comments / Updates
-- ------------------------------------------------------------
CREATE TABLE comments (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    event_id    INT  NOT NULL,
    user_id     INT  NOT NULL,
    body        TEXT NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Notifications
-- ------------------------------------------------------------
CREATE TABLE notifications (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL,
    type        VARCHAR(50)  NOT NULL,
    message     VARCHAR(500) NOT NULL,
    link        VARCHAR(255) NULL,
    is_read     TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Seed data
-- ------------------------------------------------------------
INSERT INTO categories (name) VALUES
  ('Conference'), ('Workshop'), ('Meetup'), ('Webinar'),
  ('Social'), ('Sports'), ('Music'), ('Other');

INSERT INTO tags (name) VALUES
  ('Technology'), ('Business'), ('Design'), ('Marketing'),
  ('Health'), ('Education'), ('Networking'), ('Entertainment');

-- Default admin account  (password: Admin123!)
INSERT INTO users (username, email, password, role) VALUES
  ('admin', 'admin@eventmanager.local',
   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');
