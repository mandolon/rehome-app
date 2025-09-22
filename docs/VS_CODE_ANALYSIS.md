# Backend Architecture Analysis - VS Code Analysis Report

## Executive Summary
The rehome-app employs a **dual backend architecture** with both Node.js/Express and Laravel APIs, creating a complex but feature-rich backend system. The analysis reveals a sophisticated real-time collaborative platform with WebSocket integration, comprehensive database schema, and robust API endpoints.

## 1. API Endpoints Discovery

### Node.js/Express Backend (`server/routes.ts`)
**Task Management API:**
- `GET /api/tasks` - List all tasks
- `GET /api/tasks/all` - Get all tasks (different endpoint)
- `GET /api/tasks/:taskId` - Get specific task
- `POST /api/tasks` - Create new task
- `PUT /api/tasks/:taskId` - Update existing task
- `DELETE /api/tasks/:taskId` - Soft delete task
- `DELETE /api/tasks/:taskId/permanent` - Permanent delete task
- `PUT /api/tasks/:taskId/restore` - Restore deleted task
- `GET /api/tasks/:taskId/messages` - Get task messages/comments
- `POST /api/tasks/:taskId/messages` - Add task message/comment

**Project Management API:**
- `GET /api/projects` - List all projects
- `GET /api/projects/:projectId` - Get specific project
- `POST /api/projects` - Create new project
- `DELETE /api/projects/:projectId` - Delete project

**Search & Utility API:**
- `GET /api/search` - Global search functionality
- `GET /api/work-records` - Get work records

**Trash/Recovery System:**
- `GET /api/trash` - List deleted items
- `POST /api/trash` - Move item to trash
- `POST /api/trash/:trashItemId/restore` - Restore from trash
- `DELETE /api/trash/:trashItemId` - Permanent delete from trash
- `DELETE /api/trash` - Empty entire trash

### Laravel Backend (`laravel-api/routes/api.php`)
**User Management API:**
- `GET /api/user` - Get authenticated user (with auth:sanctum middleware)
- `GET /api/users` - List all users
- `POST /api/users` - Create user
- `GET /api/users/{id}` - Get specific user
- `PUT /api/users/{id}` - Update user
- `DELETE /api/users/{id}` - Delete user

**Task Management API (Laravel duplicate):**
- `GET /api/tasks/` - List tasks
- `GET /api/tasks/all` - Get all tasks
- `POST /api/tasks/` - Create task
- `GET /api/tasks/{taskId}` - Get task
- `PUT /api/tasks/{taskId}` - Update task
- `DELETE /api/tasks/{taskId}` - Delete task
- `DELETE /api/tasks/{taskId}/permanent` - Force delete
- `POST /api/tasks/{taskId}/restore` - Restore task
- `PATCH /api/tasks/{taskId}/status` - Update task status
- `POST /api/tasks/{taskId}/archive` - Archive task

**Task Messaging (Laravel):**
- `GET /api/tasks/{taskId}/messages` - Get task messages
- `POST /api/tasks/{taskId}/messages` - Create message
- `PUT /api/tasks/{taskId}/messages/{messageId}` - Update message
- `DELETE /api/tasks/{taskId}/messages/{messageId}` - Delete message

**Project-Task Relations:**
- `GET /api/projects/{projectId}/tasks` - Get tasks for specific project

## 2. Real-Time Implementation Analysis

### WebSocket Server Architecture
**Location:** `server/routes.ts` lines 11-30
**Implementation:** Native WebSocket Server (`ws` package)
- **Endpoint:** `/ws` path on HTTP server
- **Connection Management:** Auto-connect/disconnect logging
- **Broadcasting:** Real-time updates to all connected clients
- **Message Format:** JSON structured events with event type and data

### WebSocket Client Implementation
**Location:** `client/src/hooks/useWebSocket.tsx`
**Features:**
- **Auto-reconnection:** Built-in reconnection logic on disconnect
- **Message Parsing:** JSON message parsing with error handling
- **Connection State:** Tracks connection status for UI feedback
- **Event Handling:** Configurable message handler callbacks

### Real-Time Event Integration
**Location:** `client/src/hooks/useTaskOperations.tsx`
**WebSocket Events Handled:**
- `task_created` - New task notifications
- `task_updated` - Task modification updates  
- `task_deleted` - Task deletion notifications
- `task_restored` - Task restoration events
- **Query Invalidation:** Automatic React Query cache updates on WebSocket events

### WebSocket URL Construction Pattern
```typescript
const wsUrl = `${window.location.protocol === 'https:' ? 'wss:' : 'ws:'}//
${window.location.hostname}:${window.location.port}/ws?token=123`;
```

## 3. Database Architecture Analysis

### Database Technology Stack
- **Primary Database:** PostgreSQL (via Neon serverless)
- **ORM:** Drizzle ORM for Node.js backend
- **Schema Location:** `shared/schema.ts` (shared between client/server)
- **Migration System:** Drizzle Kit for schema management

### Core Database Tables

#### Users Table
```sql
users(
  id: serial PRIMARY KEY,
  username: text NOT NULL UNIQUE,
  password: text NOT NULL
) WITH INDEX ON username
```

#### Tasks Table (Most Complex)
```sql
tasks(
  id: serial PRIMARY KEY,
  task_id: text NOT NULL UNIQUE,
  title: text NOT NULL,
  project_id: text NOT NULL,
  project: text,
  estimated_completion: text,
  date_created: text,
  due_date: text,
  assignee: jsonb,
  has_attachment: boolean DEFAULT false,
  collaborators: jsonb DEFAULT '[]',
  status: text,
  archived: boolean DEFAULT false,
  created_by: text NOT NULL,
  created_at: timestamp DEFAULT NOW(),
  updated_at: timestamp DEFAULT NOW(),
  deleted_at: text,
  deleted_by: text,
  description: text,
  marked_complete: timestamp,
  marked_complete_by: text,
  time_logged: text DEFAULT '0',
  work_record: boolean DEFAULT false
) WITH INDEXES ON (title, description, created_by)
```

#### Projects Table
```sql
projects(
  id: serial PRIMARY KEY,
  project_id: text NOT NULL UNIQUE,
  title: text NOT NULL,
  description: text,
  status: text DEFAULT 'in_progress',
  client_name: text,
  project_address: text,
  start_date: text,
  due_date: text,
  estimated_completion: text,
  priority: text DEFAULT 'medium',
  created_by: text NOT NULL,
  created_at: timestamp DEFAULT NOW(),
  updated_at: timestamp DEFAULT NOW(),
  deleted_at: text,
  deleted_by: text
) WITH INDEXES ON (title, description, client_name)
```

#### Task Messages Table
```sql
task_messages(
  id: uuid PRIMARY KEY DEFAULT random(),
  task_id: text NOT NULL,
  user_id: text NOT NULL,
  user_name: text NOT NULL,
  message: text NOT NULL,
  created_at: timestamp DEFAULT NOW(),
  updated_at: timestamp DEFAULT NOW()
)
```

#### Trash Items Table (Soft Delete System)
```sql
trash_items(
  id: uuid PRIMARY KEY DEFAULT random(),
  item_type: text NOT NULL,
  item_id: text NOT NULL,
  title: text NOT NULL,
  description: text,
  metadata: jsonb,
  deleted_by: text NOT NULL,
  deleted_at: timestamp DEFAULT NOW(),
  original_data: jsonb NOT NULL
)
```

### Database Access Patterns

#### Drizzle ORM Usage
**Configuration:** `server/db.ts`
- **Connection:** Neon serverless HTTP connection (not WebSocket)
- **Schema Import:** Uses shared schema from `@shared/schema`
- **Error Handling:** Environment variable validation

#### In-Memory Storage Fallback
**Location:** `server/storage.ts`
**Purpose:** Development/fallback data storage
**Implementation:** Map-based storage with persistence simulation
- **Task Storage:** `Map<string, Task>` for tasks
- **Project Storage:** `Map<string, Project>` for projects
- **Trash System:** `Map<string, TrashItem>` for deleted items

#### Laravel Database Configuration
**Location:** `laravel-api/config/database.php`
**Database Support:**
- PostgreSQL (primary)
- MySQL (secondary)
- SQLite (development)
- **Session Storage:** Database-backed sessions
- **Unix Socket Support:** For local connections

## 4. Authentication Architecture

### Session Management
**Primary Method:** Express Session with PostgreSQL storage
**Dependencies:**
- `express-session` v1.18.1
- `connect-pg-simple` v10.0.0 (PostgreSQL session store)
- Type definitions: `@types/express-session`, `@types/passport`, `@types/passport-local`

### Authentication Flow
**Current State:** Session-based authentication infrastructure present
**Laravel Integration:** Sanctum middleware for API authentication
**Frontend Integration:** Protected routes via `ProtectedRoute` component

### Session Configuration Pattern
```typescript
// session configuration uses connect-pg-simple
// for PostgreSQL session persistence
```

## 5. Dependency Analysis

### Backend-Specific Dependencies

#### Node.js/Express Backend
**Production Dependencies:**
- `express` v4.21.2 - Web application framework
- `express-session` v1.18.1 - Session middleware
- `ws` v8.18.0 - WebSocket implementation
- `drizzle-orm` v0.39.1 - TypeScript ORM
- `@neondatabase/serverless` v0.10.4 - Serverless PostgreSQL
- `zod` v3.24.2 - Schema validation
- `openai` v5.5.1 - AI integration (ready for use)

**Development Dependencies:**
- `@types/express` v4.17.21
- `@types/express-session` v1.18.0
- `@types/ws` v8.5.13
- `drizzle-kit` v0.30.4 - Database migration tool
- `tsx` v4.19.1 - TypeScript execution
- `esbuild` v0.25.0 - Fast bundler

#### Laravel Backend
**PHP Dependencies:**
- `php` ^8.2 (requirement)
- `laravel/framework` ^12.0 - Latest Laravel framework
- `laravel/tinker` ^2.10.1 - REPL for Laravel

**Development Dependencies:**
- `fakerphp/faker` ^1.23 - Test data generation
- `phpunit/phpunit` ^11.5.3 - Testing framework
- `laravel/pint` ^1.13 - Code style fixer
- `laravel/sail` ^1.41 - Docker development environment

### Version Conflicts & Duplicates
**Identified Issues:**
1. **Dual Task Management:** Both Express and Laravel implement full task CRUD APIs
2. **Authentication Overlap:** Express session + Laravel Sanctum creates complexity
3. **Database Duplication:** Drizzle schema + Laravel migrations potential conflict
4. **WebSocket Isolation:** Only Express backend handles WebSocket, Laravel doesn't integrate

## 6. Code Metrics & Quality Issues

### TODO/FIXME Analysis
**Found TODOs:**
- `SidebarProjectSection.tsx:203` - "TODO: Implement rename functionality"
- `SidebarProjectSection.tsx:206` - "TODO: Implement duplicate functionality"  
- `SidebarProjectSection.tsx:209` - "TODO: Implement archive functionality"
- `WhiteboardsHeader.tsx:33` - "TODO: Implement create whiteboard functionality"

### Architecture Concerns
1. **Backend Redundancy:** Dual backend creates maintenance overhead
2. **Session Complexity:** Multiple authentication systems
3. **WebSocket Limitation:** Only integrated with Express backend
4. **API Versioning:** No versioning strategy evident
5. **Error Handling:** Inconsistent error handling patterns

## 7. Frontend-Backend Relationship Mapping

### React Query Integration
**Primary Pattern:** TanStack Query for API state management
**Real-time Updates:** WebSocket events trigger query invalidation
**Optimistic Updates:** Mutations with optimistic UI updates

### API Call Patterns
**Base URLs:** Dynamic based on environment
**Authentication:** Session-based (cookies)
**Error Handling:** Standardized error responses
**Caching:** React Query with 30-minute stale time

### Component-API Relationships
- `useTaskOperations` → Express Task API + WebSocket
- `useTaskBoard` → Express Task API
- `SidebarProjectSection` → Express Project API
- Search components → Express Search API
- Trash management → Express Trash API

## 8. Recommendations

### Immediate Actions
1. **Consolidate Backend:** Choose either Express or Laravel, not both
2. **Unify Authentication:** Single authentication system
3. **WebSocket Integration:** Extend to chosen backend
4. **API Documentation:** Generate OpenAPI specs
5. **Error Standards:** Implement consistent error handling

### Long-term Improvements
1. **API Versioning:** Implement proper API versioning
2. **Database Migration:** Single migration system
3. **Testing Strategy:** API testing framework
4. **Performance Monitoring:** Add metrics and logging
5. **Security Audit:** Authentication and authorization review

## Conclusion
The backend architecture demonstrates sophisticated real-time capabilities and comprehensive data management, but suffers from architectural duplication between Express and Laravel backends. The WebSocket implementation is well-designed, and the database schema supports complex project management workflows. Priority should be given to backend consolidation and authentication unification.