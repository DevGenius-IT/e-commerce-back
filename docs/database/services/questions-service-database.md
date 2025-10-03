# Questions Service Database Documentation

## Table of Contents
- [Overview](#overview)
- [Database Information](#database-information)
- [Entity Relationship Diagram](#entity-relationship-diagram)
- [Table Schemas](#table-schemas)
- [FAQ System Architecture](#faq-system-architecture)
- [Moderation Workflow](#moderation-workflow)
- [Helpfulness Scoring](#helpfulness-scoring)
- [Events Published](#events-published)
- [Cross-Service References](#cross-service-references)
- [Indexes and Performance](#indexes-and-performance)

## Overview

The questions-service database (`questions_service_db`) manages a product-focused FAQ system where customers can ask questions about products and receive answers from other customers or official store representatives. This service provides community-driven product information, moderation capabilities, and helpfulness voting to surface the most useful answers.

**Service:** questions-service
**Database:** questions_service_db
**External Port:** 3324
**Total Tables:** 6 (2 core business, 4 Laravel standard)

**Key Capabilities:**
- Product-specific question management
- Answer submission and replies
- Publication and moderation controls
- Helpfulness voting system
- Official answer designation
- User-generated content tracking
- Community-driven FAQ building

## Database Information

### Connection Details
```bash
Host: localhost (in Docker network: mysql-questions)
Port: 3324 (external), 3306 (internal)
Database: questions_service_db
Charset: utf8mb4
Collation: utf8mb4_unicode_ci
Engine: InnoDB
```

### Environment Configuration
```bash
DB_CONNECTION=mysql
DB_HOST=mysql-questions
DB_PORT=3306
DB_DATABASE=questions_service_db
DB_USERNAME=questions_user
DB_PASSWORD=questions_pass
```

## Entity Relationship Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                   QUESTIONS SERVICE DATABASE                        │
│                  questions_service_db (6 tables)                    │
└─────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│                      PRODUCT FAQ SYSTEM                              │
└──────────────────────────────────────────────────────────────────────┘

                    ┌─────────────────────────────┐
                    │        questions            │
                    ├─────────────────────────────┤
                    │ id               PK         │
                    │ user_id          FK ────────┼───> auth-service.users.id
                    │ product_id       FK ────────┼───> products-service.products.id
                    │ question         TEXT       │
                    │ is_published     BOOL       │ DEFAULT 0 (pending moderation)
                    │ created_at                  │
                    │ updated_at                  │
                    └──────────┬──────────────────┘
                               │
                               │ question_id (FK CASCADE)
                               ▼
                    ┌─────────────────────────────┐
                    │         answers             │
                    ├─────────────────────────────┤
                    │ id               PK         │
                    │ question_id      FK ────────┤─────┐
                    │ user_id          FK ────────┼───> auth-service.users.id
                    │ answer           TEXT       │     │
                    │ is_official      BOOL       │ DEFAULT 0
                    │ is_helpful_count INT        │ DEFAULT 0 (vote counter)
                    │ created_at                  │     │
                    │ updated_at                  │     │
                    └─────────────────────────────┘     │
                                                        │
                                                        │
                           ┌────────────────────────────┘
                           │
                           └─ One-to-Many Relationship
                              (One question → Many answers)

┌──────────────────────────────────────────────────────────────────────┐
│                    LARAVEL STANDARD TABLES                           │
└──────────────────────────────────────────────────────────────────────┘

    ┌──────────────┐      ┌──────────────┐      ┌──────────────┐
    │    users     │      │    cache     │      │ cache_locks  │
    │ (reference)  │      │              │      │              │
    └──────────────┘      └──────────────┘      └──────────────┘

                           ┌──────────────┐
                           │     jobs     │
                           │ (queue)      │
                           └──────────────┘

CROSS-SERVICE REFERENCES (Virtual):
─────────────────────────────────────────────────────────────────
questions.user_id        → auth-service.users.id (question author)
questions.product_id     → products-service.products.id
answers.user_id          → auth-service.users.id (answer author)
answers.question_id      → questions.id (Internal FK CASCADE)

LEGEND:
────────  Internal Relationship / Foreign Key
───────   Virtual Foreign Key (Cross-Service)
PK        Primary Key
FK        Foreign Key
BOOL      Boolean/TinyInt
```

## Table Schemas

### 1. questions (CORE)
Product-specific questions from customers.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Question ID |
| user_id | BIGINT UNSIGNED | NOT NULL | Question author (auth-service) |
| product_id | BIGINT UNSIGNED | NOT NULL | Related product (products-service) |
| question | TEXT | NOT NULL | Question content |
| is_published | BOOLEAN | NOT NULL, DEFAULT 0 | Publication status (moderation flag) |
| created_at | TIMESTAMP | NULLABLE | Question submission timestamp |
| updated_at | TIMESTAMP | NULLABLE | Last update timestamp |

**Cross-Service References (Virtual):**
- user_id → auth-service.users.id (no FK constraint)
- product_id → products-service.products.id (no FK constraint)

**Indexes:**
- PRIMARY KEY (id)
- INDEX (user_id)
- INDEX (product_id)
- INDEX (is_published, created_at)
- INDEX (product_id, is_published)

**Business Rules:**
- is_published defaults to 0 (pending moderation)
- Only published questions visible to public
- User can ask multiple questions per product
- No soft deletes (hard delete if necessary)
- question field required and non-empty

**Model Relationships:**
- hasMany: Answer (via question_id)
- Virtual: belongsTo User (auth-service)
- Virtual: belongsTo Product (products-service)

**Validation Rules:**
```php
'user_id' => 'required|integer',
'product_id' => 'required|integer|exists_in_products_service',
'question' => 'required|string|min:10|max:1000',
'is_published' => 'boolean'
```

---

### 2. answers (CORE)
Answers to product questions from community or official sources.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | BIGINT UNSIGNED | PK, AUTO_INCREMENT | Answer ID |
| question_id | BIGINT UNSIGNED | NOT NULL, FK | Parent question (questions.id) |
| user_id | BIGINT UNSIGNED | NOT NULL | Answer author (auth-service) |
| answer | TEXT | NOT NULL | Answer content |
| is_official | BOOLEAN | NOT NULL, DEFAULT 0 | Official store answer flag |
| is_helpful_count | INTEGER | NOT NULL, DEFAULT 0 | Number of helpfulness votes |
| created_at | TIMESTAMP | NULLABLE | Answer submission timestamp |
| updated_at | TIMESTAMP | NULLABLE | Last update timestamp |

**Foreign Keys:**
- question_id → questions(id) ON DELETE CASCADE

**Cross-Service References (Virtual):**
- user_id → auth-service.users.id (no FK constraint)

**Indexes:**
- PRIMARY KEY (id)
- INDEX (question_id)
- INDEX (user_id)
- INDEX (is_official)
- INDEX (is_helpful_count)
- INDEX (question_id, is_helpful_count)

**Business Rules:**
- Cascade delete when parent question deleted
- is_official flag set by admin/staff only
- is_helpful_count incremented by voting system
- Multiple answers per question allowed
- No soft deletes
- Official answers prioritized in display

**Model Relationships:**
- belongsTo: Question (via question_id)
- Virtual: belongsTo User (auth-service)

**Validation Rules:**
```php
'question_id' => 'required|integer|exists:questions,id',
'user_id' => 'required|integer',
'answer' => 'required|string|min:10|max:2000',
'is_official' => 'boolean',
'is_helpful_count' => 'integer|min:0'
```

**Calculated Display Order:**
```php
// Answer ordering logic
ORDER BY
    is_official DESC,           // Official answers first
    is_helpful_count DESC,      // Then by helpfulness
    created_at ASC              // Then by submission time
```

---

### 3. users (Reference Table)
Local reference cache for user data from auth-service.

**Purpose:** Cache user display names and basic info for performance.

**Synchronization:**
- Listen to UserCreated: Cache user data
- Listen to UserUpdated: Update cached data
- Listen to UserDeleted: Mark as inactive or delete cache

**Note:** Not strictly required if auth-service queries are fast enough.

---

### 4. cache (Laravel Standard)
Laravel cache storage table.

**Purpose:** Store cached query results, rate limiting data, etc.

---

### 5. cache_locks (Laravel Standard)
Laravel cache lock mechanisms.

**Purpose:** Distributed locking for cache operations.

---

### 6. jobs (Laravel Standard)
Laravel queue jobs table.

**Purpose:** Asynchronous job processing (email notifications, event publishing).

## FAQ System Architecture

### Question Lifecycle

```
┌─────────────────────────────────────────────────────────────┐
│                    QUESTION LIFECYCLE                       │
└─────────────────────────────────────────────────────────────┘

[User] → Submit Question
    ↓
┌─────────────────────┐
│ Question Created    │
│ is_published = 0    │ -- Pending moderation
└──────────┬──────────┘
           │
           │ [Automatic OR Manual Moderation]
           ▼
    ┌──────────────────────┐
    │ Moderation Check     │
    │ - Spam detection     │
    │ - Content policy     │
    │ - Language check     │
    └──────┬────────┬──────┘
           │        │
      PASS │        │ REJECT
           ▼        ▼
    ┌──────────┐  ┌──────────┐
    │Published │  │ Rejected │
    │= 1       │  │Deleted   │
    └────┬─────┘  └──────────┘
         │
         │ [Question Visible to Public]
         ▼
    ┌──────────────────┐
    │ Answers Collected│
    │ - Community      │
    │ - Official       │
    └──────────────────┘
```

### Answer Lifecycle

```
┌─────────────────────────────────────────────────────────────┐
│                     ANSWER LIFECYCLE                        │
└─────────────────────────────────────────────────────────────┘

[User OR Staff] → Submit Answer
    ↓
┌─────────────────────┐
│ Answer Created      │
│ is_official = ?     │ -- 0 for users, 1 for staff
│ is_helpful_count=0  │
└──────────┬──────────┘
           │
           │ [Immediately Visible - No Moderation]
           ▼
    ┌──────────────────┐
    │ Answer Published │
    └──────────┬───────┘
               │
               │ [Community Voting]
               ▼
    ┌──────────────────────────┐
    │ Helpfulness Accumulation │
    │ is_helpful_count += 1    │
    └──────────┬───────────────┘
               │
               │ [Display Ranking]
               ▼
    ┌─────────────────────────────────┐
    │ Answer Ranking Algorithm        │
    │ 1. Official answers first       │
    │ 2. Highest helpfulness count    │
    │ 3. Oldest first (created_at)    │
    └─────────────────────────────────┘
```

### Product FAQ Page Structure

```
┌──────────────────────────────────────────────────────────────┐
│               PRODUCT FAQ PAGE DISPLAY                       │
└──────────────────────────────────────────────────────────────┘

[Product XYZ Page]
    │
    ├─ [Questions & Answers Section]
    │   │
    │   ├─ Question #1 (is_published = 1)
    │   │   ├─ Posted by: User123 (via user_id)
    │   │   ├─ Posted on: 2025-10-03
    │   │   │
    │   │   ├─ Answer #1 (is_official = 1) ⭐ OFFICIAL
    │   │   │   ├─ Store Staff Response
    │   │   │   └─ 15 people found this helpful
    │   │   │
    │   │   ├─ Answer #2 (is_official = 0)
    │   │   │   ├─ Community Member Response
    │   │   │   └─ 8 people found this helpful
    │   │   │
    │   │   └─ Answer #3 (is_official = 0)
    │   │       ├─ Community Member Response
    │   │       └─ 2 people found this helpful
    │   │
    │   └─ Question #2 (is_published = 1)
    │       └─ [Similar structure...]
    │
    └─ [Ask a Question Button]
```

## Moderation Workflow

### Question Moderation Process

```
┌──────────────────────────────────────────────────────────────┐
│               QUESTION MODERATION WORKFLOW                   │
└──────────────────────────────────────────────────────────────┘

[User Submits Question]
    ↓
┌─────────────────────┐
│ Store in Database   │
│ is_published = 0    │
└──────────┬──────────┘
           │
           │ Publish QuestionAsked Event
           ▼
┌────────────────────────────────────┐
│ Moderation Queue                   │
│ (Admin Dashboard OR Automated)     │
└──────────┬─────────────────────────┘
           │
           ├─ [Automatic Moderation - Optional]
           │   ├─ Spam detection (keywords)
           │   ├─ Profanity filter
           │   ├─ Duplicate detection
           │   └─ Language validation
           │
           ├─ [Manual Moderation - Required]
           │   ├─ Admin reviews question
           │   ├─ Checks: relevance, clarity, policy
           │   └─ Decision: Approve OR Reject
           │
           ▼
    ┌──────────────────┐
    │ Moderation Result│
    └──────┬────────┬──┘
           │        │
     APPROVE       REJECT
           │        │
           ▼        ▼
    ┌──────────┐  ┌──────────────┐
    │ Publish  │  │ Delete OR    │
    │ Set = 1  │  │ Keep Hidden  │
    └────┬─────┘  └──────────────┘
         │
         │ Publish QuestionPublished Event
         ▼
    [Question Visible to All Users]
```

### Moderation API Endpoints

```php
// Admin moderation endpoints
POST   /admin/questions/{id}/approve    // Set is_published = 1
POST   /admin/questions/{id}/reject     // Delete or keep hidden
GET    /admin/questions/pending         // List unpublished questions
PATCH  /admin/questions/{id}            // Edit question content
```

### Automatic Moderation Rules (Optional)

```php
// AutoModerationService.php

public function autoModerate(Question $question): bool
{
    // Rule 1: Spam keyword detection
    if ($this->containsSpam($question->question)) {
        $question->delete();
        return false;
    }

    // Rule 2: Profanity filter
    if ($this->containsProfanity($question->question)) {
        $question->delete();
        return false;
    }

    // Rule 3: Duplicate detection
    if ($this->isDuplicate($question)) {
        // Don't publish, flag for manual review
        return false;
    }

    // Rule 4: Minimum quality check
    if (strlen($question->question) < 10) {
        $question->delete();
        return false;
    }

    // Auto-approve if passes all checks
    $question->is_published = true;
    $question->save();

    return true;
}
```

## Helpfulness Scoring

### Voting System Architecture

```
┌──────────────────────────────────────────────────────────────┐
│               HELPFULNESS VOTING SYSTEM                      │
└──────────────────────────────────────────────────────────────┘

[User Views Answer]
    ↓
┌────────────────────────────────┐
│ "Was this answer helpful?"     │
│ [Yes] [No]                     │
└──────────┬─────────────────────┘
           │
           │ User clicks "Yes"
           ▼
┌────────────────────────────────┐
│ Vote Validation                │
│ - User authenticated?          │
│ - Already voted? (cookie/DB)   │
│ - Rate limiting                │
└──────────┬─────────────────────┘
           │
           │ VALID
           ▼
┌────────────────────────────────┐
│ Increment is_helpful_count     │
│ UPDATE answers                 │
│ SET is_helpful_count += 1      │
│ WHERE id = ?                   │
└──────────┬─────────────────────┘
           │
           │ Publish AnswerMarkedHelpful Event
           ▼
┌────────────────────────────────┐
│ Update Answer Ranking          │
│ Re-sort answers by:            │
│ 1. is_official DESC            │
│ 2. is_helpful_count DESC       │
│ 3. created_at ASC              │
└────────────────────────────────┘
```

### Vote Tracking Implementation

**Option 1: Cookie-Based (Simple)**
```php
// AnswerVoteController.php

public function markHelpful(Answer $answer, Request $request): JsonResponse
{
    // Check if already voted (cookie)
    $voted = $request->cookie("voted_answer_{$answer->id}");

    if ($voted) {
        return response()->json(['error' => 'Already voted'], 409);
    }

    // Increment vote count
    $answer->increment('is_helpful_count');

    // Set cookie (expires in 1 year)
    return response()->json(['success' => true])
        ->cookie("voted_answer_{$answer->id}", true, 525600);
}
```

**Option 2: Database-Tracked (Future Enhancement)**
```sql
-- answer_votes table (optional future feature)
CREATE TABLE answer_votes (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    answer_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    vote_type ENUM('helpful', 'not_helpful') NOT NULL,
    created_at TIMESTAMP,
    UNIQUE KEY (answer_id, user_id),
    FOREIGN KEY (answer_id) REFERENCES answers(id) ON DELETE CASCADE
);
```

### Answer Ranking Query

```php
// QuestionService.php

public function getAnswersForQuestion(int $questionId): Collection
{
    return Answer::where('question_id', $questionId)
        ->orderBy('is_official', 'DESC')      // Official first
        ->orderBy('is_helpful_count', 'DESC') // Most helpful next
        ->orderBy('created_at', 'ASC')        // Oldest first
        ->get();
}
```

## Events Published

The questions-service publishes events to RabbitMQ for inter-service communication.

### Event Schema

All events follow standard message format:
```json
{
  "event": "question.asked",
  "timestamp": "2025-10-03T14:30:00Z",
  "data": {
    "question_id": 42,
    "user_id": 7,
    "product_id": 23
  }
}
```

### 1. QuestionAsked
**Queue:** questions.asked
**Published:** When new question submitted

**Payload:**
```json
{
  "event": "question.asked",
  "data": {
    "question_id": 42,
    "user_id": 7,
    "product_id": 23,
    "question": "Does this laptop support 4K external displays?",
    "is_published": false,
    "created_at": "2025-10-03T14:30:00Z"
  }
}
```

**Consumers:**
- newsletters-service: Notify admin of new question (moderation queue)
- analytics-service: Track question metrics (future)
- products-service: Update product FAQ count (future)

---

### 2. QuestionPublished
**Queue:** questions.published
**Published:** When question approved and published

**Payload:**
```json
{
  "event": "question.published",
  "data": {
    "question_id": 42,
    "user_id": 7,
    "product_id": 23,
    "question": "Does this laptop support 4K external displays?",
    "published_at": "2025-10-03T15:00:00Z"
  }
}
```

**Consumers:**
- newsletters-service: Notify question author of approval
- products-service: Update product FAQ visibility
- search-service: Index question for search (future)

---

### 3. QuestionAnswered
**Queue:** questions.answered
**Published:** When new answer submitted

**Payload:**
```json
{
  "event": "question.answered",
  "data": {
    "answer_id": 15,
    "question_id": 42,
    "user_id": 12,
    "answer": "Yes, it supports up to two 4K displays via USB-C ports.",
    "is_official": false,
    "created_at": "2025-10-03T16:00:00Z"
  }
}
```

**Consumers:**
- newsletters-service: Notify question author of new answer
- products-service: Update product FAQ stats

---

### 4. OfficialAnswerProvided
**Queue:** questions.official_answer
**Published:** When staff provides official answer

**Payload:**
```json
{
  "event": "question.official_answer",
  "data": {
    "answer_id": 16,
    "question_id": 42,
    "user_id": 3,
    "answer": "Official Store Response: Yes, supports dual 4K@60Hz via Thunderbolt 4.",
    "is_official": true,
    "created_at": "2025-10-03T17:00:00Z"
  }
}
```

**Consumers:**
- newsletters-service: Notify question author of official response
- analytics-service: Track official answer response time
- products-service: Highlight FAQ with official answer

---

### 5. AnswerMarkedHelpful
**Queue:** questions.answer_helpful
**Published:** When answer receives helpfulness vote

**Payload:**
```json
{
  "event": "answer.marked_helpful",
  "data": {
    "answer_id": 15,
    "question_id": 42,
    "previous_count": 4,
    "new_count": 5,
    "voted_at": "2025-10-03T18:00:00Z"
  }
}
```

**Consumers:**
- analytics-service: Track answer quality metrics
- gamification-service: Award points to answer author (future)

---

### RabbitMQ Configuration

**Exchange:** questions_exchange (topic)
**Routing Keys:**
- question.asked
- question.published
- question.answered
- question.official_answer
- answer.marked_helpful

**Consumer Queues:**
- newsletters-service: questions.events.newsletters
- products-service: questions.events.products
- analytics-service: questions.events.analytics

## Cross-Service References

### References TO Other Services

#### auth-service (users)
```sql
-- questions table
questions.user_id → auth-service.users.id (virtual FK)

-- answers table
answers.user_id → auth-service.users.id (virtual FK)
```

**Synchronization:**
- Validate user exists before question/answer creation
- Cache user display names for performance
- Listen to UserDeleted: Handle gracefully (keep questions/answers, mark as "Deleted User")

**Query Pattern:**
```php
// Fetch user details when displaying questions
$userDetails = Http::get("http://auth-service/api/users/{$question->user_id}");
```

---

#### products-service (products)
```sql
-- questions table
questions.product_id → products-service.products.id (virtual FK)
```

**Synchronization:**
- Validate product exists before question creation
- Listen to ProductDeleted: Cascade delete questions OR keep for history
- Cache product names for display

**Query Pattern:**
```php
// Validate product before question submission
$product = Http::get("http://products-service/api/products/{$productId}");

if (!$product->successful()) {
    throw new ProductNotFoundException();
}
```

---

### Referenced BY Other Services

#### products-service (FAQ Count)
```sql
-- products-service may cache FAQ count
-- Future enhancement: denormalize question count

CREATE TABLE products (
    ...
    faq_count INT DEFAULT 0,  -- Count of published questions
    faq_answered_count INT DEFAULT 0  -- Count with at least 1 answer
);
```

**Synchronization:**
- Listen to QuestionPublished: Increment faq_count
- Listen to QuestionAnswered: Update faq_answered_count

---

#### newsletters-service (Notifications)
Questions-service events trigger email notifications:

**Email Types:**
1. **New Question Submitted** → Admin notification (moderation)
2. **Question Approved** → Author notification
3. **Question Answered** → Author notification
4. **Official Answer Provided** → Author notification (priority)

---

## Indexes and Performance

### Strategic Indexes

#### questions Table
```sql
INDEX (user_id)                      -- User question history
INDEX (product_id)                   -- Product FAQ page
INDEX (is_published, created_at)     -- Published questions sorted
INDEX (product_id, is_published)     -- Product FAQ filtering
```

**Query Optimization:**
```sql
-- Product FAQ page (published questions only)
SELECT * FROM questions
WHERE product_id = 23
  AND is_published = 1
ORDER BY created_at DESC;

-- User question history
SELECT * FROM questions
WHERE user_id = 7
ORDER BY created_at DESC
LIMIT 20;

-- Moderation queue (unpublished questions)
SELECT * FROM questions
WHERE is_published = 0
ORDER BY created_at ASC;

-- Recent published questions
SELECT * FROM questions
WHERE is_published = 1
ORDER BY created_at DESC
LIMIT 20;
```

---

#### answers Table
```sql
INDEX (question_id)                        -- Question answers lookup
INDEX (user_id)                            -- User answer history
INDEX (is_official)                        -- Official answers filter
INDEX (is_helpful_count)                   -- Most helpful sorting
INDEX (question_id, is_helpful_count)      -- Answer ranking query
```

**Query Optimization:**
```sql
-- Get all answers for question (with ranking)
SELECT * FROM answers
WHERE question_id = 42
ORDER BY is_official DESC,
         is_helpful_count DESC,
         created_at ASC;

-- User answer history
SELECT * FROM answers
WHERE user_id = 12
ORDER BY created_at DESC
LIMIT 20;

-- Official answers only
SELECT a.*, q.question
FROM answers a
JOIN questions q ON a.question_id = q.id
WHERE a.is_official = 1
ORDER BY a.created_at DESC;

-- Most helpful community answers
SELECT * FROM answers
WHERE is_official = 0
ORDER BY is_helpful_count DESC
LIMIT 10;
```

---

### Composite Indexes

#### questions Table
```sql
INDEX (product_id, is_published, created_at)  -- Product FAQ page optimization
```

**Benefits:**
- Single index for product FAQ queries
- Efficient filtering by publication status
- Built-in date sorting

**Example Query:**
```sql
-- Optimized product FAQ page
SELECT * FROM questions
WHERE product_id = 23
  AND is_published = 1
ORDER BY created_at DESC
LIMIT 10;
```

---

#### answers Table
```sql
INDEX (question_id, is_official, is_helpful_count)  -- Answer ranking optimization
```

**Benefits:**
- Single index for answer display query
- Covers official flag filtering
- Supports helpfulness sorting

**Example Query:**
```sql
-- Optimized answer ranking
SELECT * FROM answers
WHERE question_id = 42
ORDER BY is_official DESC,
         is_helpful_count DESC;
```

---

### Performance Recommendations

1. **Product FAQ Page:**
   - Use composite index (product_id, is_published, created_at)
   - Cache question count per product in Redis
   - Lazy load answers (load when question expanded)
   - Paginate questions (10-20 per page)

2. **Answer Ranking:**
   - Use composite index (question_id, is_official, is_helpful_count)
   - Load all answers at once (rarely >50 per question)
   - Cache popular questions/answers in Redis

3. **Moderation Queue:**
   - Index on is_published for fast filtering
   - Consider separate table for moderation logs (future)
   - Paginate pending questions

4. **User History:**
   - Index on user_id for both tables
   - Paginate results (prevent loading all history)
   - Consider archiving old questions (>2 years)

5. **Helpfulness Voting:**
   - Use atomic increment (no locking needed)
   - Rate limit votes (1 vote per user per answer)
   - Consider caching vote counts in Redis for high-traffic answers

6. **Cross-Service Queries:**
   - Cache user display names locally (users table)
   - Cache product names for question display
   - Implement circuit breakers for service calls

---

**Document Version:** 1.0
**Last Updated:** 2025-10-03
**Database Version:** MySQL 8.0
**Laravel Version:** 12.x
