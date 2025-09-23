# GitHub API Script for Backend Migration Issues
# Execute these commands using GitHub API or GitHub CLI

# =============================================================================
# 1. CREATE LABELS
# =============================================================================

# Backend label
curl -X POST \
  -H "Accept: application/vnd.github.v3+json" \
  -H "Authorization: token YOUR_GITHUB_TOKEN" \
  https://api.github.com/repos/mandolon/rehome-app/labels \
  -d '{
    "name": "backend",
    "color": "0e8a16",
    "description": "Backend-related issues and tasks"
  }'

# Frontend label
curl -X POST \
  -H "Accept: application/vnd.github.v3+json" \
  -H "Authorization: token YOUR_GITHUB_TOKEN" \
  https://api.github.com/repos/mandolon/rehome-app/labels \
  -d '{
    "name": "frontend",
    "color": "1d76db",
    "description": "Frontend-related issues and tasks"
  }'

# Auth label
curl -X POST \
  -H "Accept: application/vnd.github.v3+json" \
  -H "Authorization: token YOUR_GITHUB_TOKEN" \
  https://api.github.com/repos/mandolon/rehome-app/labels \
  -d '{
    "name": "auth",
    "color": "d93f0b",
    "description": "Authentication and authorization issues"
  }'

# Sockets label
curl -X POST \
  -H "Accept: application/vnd.github.v3+json" \
  -H "Authorization: token YOUR_GITHUB_TOKEN" \
  https://api.github.com/repos/mandolon/rehome-app/labels \
  -d '{
    "name": "sockets",
    "color": "f9d0c4",
    "description": "WebSocket and real-time communication issues"
  }'

# Docs label
curl -X POST \
  -H "Accept: application/vnd.github.v3+json" \
  -H "Authorization: token YOUR_GITHUB_TOKEN" \
  https://api.github.com/repos/mandolon/rehome-app/labels \
  -d '{
    "name": "docs",
    "color": "0075ca",
    "description": "Documentation updates and improvements"
  }'

# Task label
curl -X POST \
  -H "Accept: application/vnd.github.v3+json" \
  -H "Authorization: token YOUR_GITHUB_TOKEN" \
  https://api.github.com/repos/mandolon/rehome-app/labels \
  -d '{
    "name": "task",
    "color": "7057ff",
    "description": "General development tasks"
  }'

# Bug label
curl -X POST \
  -H "Accept: application/vnd.github.v3+json" \
  -H "Authorization: token YOUR_GITHUB_TOKEN" \
  https://api.github.com/repos/mandolon/rehome-app/labels \
  -d '{
    "name": "bug",
    "color": "d73a4a",
    "description": "Something is not working"
  }'

# High Priority label
curl -X POST \
  -H "Accept: application/vnd.github.v3+json" \
  -H "Authorization: token YOUR_GITHUB_TOKEN" \
  https://api.github.com/repos/mandolon/rehome-app/labels \
  -d '{
    "name": "high-priority",
    "color": "b60205",
    "description": "High priority issues requiring immediate attention"
  }'

# Phase 2.5 label
curl -X POST \
  -H "Accept: application/vnd.github.v3+json" \
  -H "Authorization: token YOUR_GITHUB_TOKEN" \
  https://api.github.com/repos/mandolon/rehome-app/labels \
  -d '{
    "name": "phase-2.5",
    "color": "ff6b35",
    "description": "Phase 2.5 TODO implementation tasks"
  }'

# Data Validation label
curl -X POST \
  -H "Accept: application/vnd.github.v3+json" \
  -H "Authorization: token YOUR_GITHUB_TOKEN" \
  https://api.github.com/repos/mandolon/rehome-app/labels \
  -d '{
    "name": "data-validation",
    "color": "0e8a16",
    "description": "Data validation and integrity checks"
  }'

# CI label
curl -X POST \
  -H "Accept: application/vnd.github.v3+json" \
  -H "Authorization: token YOUR_GITHUB_TOKEN" \
  https://api.github.com/repos/mandolon/rehome-app/labels \
  -d '{
    "name": "ci",
    "color": "1d76db",
    "description": "Continuous integration and testing"
  }'

# =============================================================================
# 2. CREATE MILESTONE
# =============================================================================

curl -X POST \
  -H "Accept: application/vnd.github.v3+json" \
  -H "Authorization: token YOUR_GITHUB_TOKEN" \
  https://api.github.com/repos/mandolon/rehome-app/milestones \
  -d '{
    "title": "Backend Consolidation v1 (12–14 weeks)",
    "description": "Migration to Laravel primary backend with Express WS bridge; includes Phase 2.5 TODOs, missing endpoints, auth cutover, WS protocol, data validation, CI contract tests.",
    "state": "open",
    "due_on": "2025-03-19T00:00:00Z"
  }'

# =============================================================================
# 3. CREATE ISSUES
# =============================================================================

# Issue 1: Missing Endpoints
curl -X POST \
  -H "Accept: application/vnd.github.v3+json" \
  -H "Authorization: token YOUR_GITHUB_TOKEN" \
  https://api.github.com/repos/mandolon/rehome-app/issues \
  -d '{
    "title": "[high-priority][backend] Implement 8 missing endpoints in Laravel",
    "body": "## Missing Endpoints Implementation\n\nAdd the following endpoints to Laravel backend:\n\n- `GET /api/tasks/all`\n- `DELETE /api/tasks/:id/permanent`\n- `POST /api/tasks/:id/restore`\n- `PATCH /api/tasks/:id/status`\n- `POST /api/tasks/:id/archive`\n- `PUT /api/tasks/:id/messages/:messageId`\n- `DELETE /api/tasks/:id/messages/:messageId`\n- `GET /api/projects/:id/tasks`\n\n## Requirements\n\n- **Policies**: Implement role-based access control (Admin/Team/Client)\n- **Tests**: Add Pest feature tests for all endpoints\n- **API Resources**: Use Laravel API Resources for consistent response format\n- **Pagination**: Implement pagination for list endpoints\n- **Error Format**: Standardize error response format\n- **OpenAPI**: Update OpenAPI documentation\n\n## Acceptance Criteria\n\n- [ ] All 8 endpoints implemented in Laravel\n- [ ] Role-based policies implemented\n- [ ] Pest feature tests passing\n- [ ] API Resources used for responses\n- [ ] Pagination implemented\n- [ ] Error format standardized\n- [ ] OpenAPI documentation updated",
    "labels": ["backend", "high-priority"],
    "milestone": 1
  }'

# Issue 2: Authentication Migration
curl -X POST \
  -H "Accept: application/vnd.github.v3+json" \
  -H "Authorization: token YOUR_GITHUB_TOKEN" \
  https://api.github.com/repos/mandolon/rehome-app/issues \
  -d '{
    "title": "[auth][high-priority] Fix authentication migration sequence (dual-stack → cutover)",
    "body": "## Authentication Migration Sequence Fix\n\nCurrent plan has logical flaw: migrates authentication system BEFORE frontend API calls are updated, causing users to be logged out.\n\n## Solution\n\nIntroduce feature flags and implement proper migration sequence:\n\n1. **Keep Express sessions** while frontend switches to Laravel endpoints\n2. **Implement dual authentication** (Express sessions + Laravel tokens)\n3. **Only then enable Sanctum-only** authentication\n4. **Add rollback steps** for authentication failures\n5. **Preserve user sessions** during transition\n\n## Implementation Steps\n\n- [ ] Implement feature flags for authentication system\n- [ ] Create dual authentication bridge\n- [ ] Update frontend to use Laravel endpoints with Express sessions\n- [ ] Implement session migration scripts\n- [ ] Add rollback procedures\n- [ ] Test authentication flow in both systems\n- [ ] Create user migration procedures\n\n## Acceptance Criteria\n\n- [ ] Feature flags implemented\n- [ ] Dual authentication working\n- [ ] Frontend uses Laravel endpoints with Express sessions\n- [ ] Session migration scripts created\n- [ ] Rollback procedures documented\n- [ ] Authentication flow tested\n- [ ] User migration procedures created",
    "labels": ["auth", "high-priority"],
    "milestone": 1
  }'

# Issue 3: WebSocket Bridge Protocol
curl -X POST \
  -H "Accept: application/vnd.github.v3+json" \
  -H "Authorization: token YOUR_GITHUB_TOKEN" \
  https://api.github.com/repos/mandolon/rehome-app/issues \
  -d '{
    "title": "[sockets][high-priority] Define WS bridge protocol (Laravel → Redis → Express ws)",
    "body": "## WebSocket Bridge Protocol Implementation\n\nDefine and implement inter-service communication protocol between Laravel and Express WebSocket server.\n\n## Protocol Specification\n\n### Event Schema v1\n```json\n{\n  \"type\": \"task_created|task_updated|task_deleted\",\n  \"projectId\": \"string\",\n  \"entity\": \"task|project|user\",\n  \"payload\": {},\n  \"timestamp\": \"ISO8601\",\n  \"signature\": \"string\"\n}\n```\n\n### Communication Flow\n1. **Laravel broadcasts** to Redis channel\n2. **Express WS** subscribes to Redis channel\n3. **Express WS relays** to connected clients\n4. **Authentication** for private channels\n5. **Health checks** and monitoring\n6. **Backoff & replay** mechanisms\n\n## Implementation Requirements\n\n- [ ] Define event schema v1\n- [ ] Implement Laravel Redis broadcasting\n- [ ] Create Express Redis subscriber\n- [ ] Implement WebSocket relay logic\n- [ ] Add authentication for private channels\n- [ ] Implement health checks\n- [ ] Add backoff and replay mechanisms\n- [ ] Create monitoring and alerting\n\n## Acceptance Criteria\n\n- [ ] Event schema defined and documented\n- [ ] Laravel Redis broadcasting working\n- [ ] Express Redis subscriber implemented\n- [ ] WebSocket relay functional\n- [ ] Private channel authentication working\n- [ ] Health checks implemented\n- [ ] Backoff and replay mechanisms working\n- [ ] Monitoring and alerting setup",
    "labels": ["sockets", "high-priority"],
    "milestone": 1
  }'

# Issue 4: Phase 2.5 TODOs
curl -X POST \
  -H "Accept: application/vnd.github.v3+json" \
  -H "Authorization: token YOUR_GITHUB_TOKEN" \
  https://api.github.com/repos/mandolon/rehome-app/issues \
  -d '{
    "title": "[phase-2.5] Resolve TODO/FIXME pre-migration",
    "body": "## Phase 2.5: Resolve Outstanding TODOs\n\nImplement missing features identified in codebase before migration to prevent complications.\n\n## TODO/FIXME Items to Implement\n\n### Project Operations\n- `SidebarProjectSection.tsx:203` - \"TODO: Implement rename functionality\"\n- `SidebarProjectSection.tsx:206` - \"TODO: Implement duplicate functionality\"\n- `SidebarProjectSection.tsx:209` - \"TODO: Implement archive functionality\"\n\n### Whiteboard Operations\n- `WhiteboardsHeader.tsx:33` - \"TODO: Implement create whiteboard functionality\"\n\n## Implementation Requirements\n\n- [ ] Implement project rename functionality in both systems\n- [ ] Implement project duplicate functionality in both systems\n- [ ] Implement project archive functionality in both systems\n- [ ] Implement whiteboard creation in both systems\n- [ ] Add comprehensive tests for all new features\n- [ ] Update UI to use new endpoints\n- [ ] Ensure feature parity between Express and Laravel\n\n## Acceptance Criteria\n\n- [ ] Project rename functionality working\n- [ ] Project duplicate functionality working\n- [ ] Project archive functionality working\n- [ ] Whiteboard creation functionality working\n- [ ] Tests added and passing\n- [ ] UI updated to use new endpoints\n- [ ] Feature parity validated between systems",
    "labels": ["phase-2.5", "task"],
    "milestone": 1
  }'

# Issue 5: Data Validation
curl -X POST \
  -H "Accept: application/vnd.github.v3+json" \
  -H "Authorization: token YOUR_GITHUB_TOKEN" \
  https://api.github.com/repos/mandolon/rehome-app/issues \
  -d '{
    "title": "[data-validation] Drizzle↔Eloquent data validation scripts",
    "body": "## Data Validation Scripts\n\nCreate comprehensive data validation scripts to ensure data integrity during migration from Drizzle ORM to Eloquent ORM.\n\n## Validation Requirements\n\n### Per-Table Checks\n- **Data Types**: Verify all data types map correctly\n- **JSONB Shape**: Validate complex JSONB fields\n- **Soft Deletes**: Check soft delete timestamps and metadata\n- **Foreign Keys**: Validate relationship constraints\n- **Indexes**: Ensure database indexes work correctly\n- **Data Encryption**: Test encrypted fields migration\n- **Data Compression**: Test compressed data fields\n\n## Implementation\n\n- [ ] Generate per-table validation scripts\n- [ ] Create data type compatibility checks\n- [ ] Implement JSONB field validation\n- [ ] Add soft delete validation\n- [ ] Create foreign key constraint checks\n- [ ] Implement index validation\n- [ ] Add data encryption validation\n- [ ] Create dry-run reports\n- [ ] Implement CI gate on validation failures\n\n## Acceptance Criteria\n\n- [ ] Per-table validation scripts created\n- [ ] Data type compatibility verified\n- [ ] JSONB fields validated\n- [ ] Soft deletes validated\n- [ ] Foreign keys validated\n- [ ] Indexes validated\n- [ ] Encryption validated\n- [ ] Dry-run reports generated\n- [ ] CI gate implemented",
    "labels": ["data-validation", "backend"],
    "milestone": 1
  }'

# Issue 6: Documentation Updates
curl -X POST \
  -H "Accept: application/vnd.github.v3+json" \
  -H "Authorization: token YOUR_GITHUB_TOKEN" \
  https://api.github.com/repos/mandolon/rehome-app/issues \
  -d '{
    "title": "[docs] Update UNIFIED_MIGRATION_PLAN + DECISION_LOG",
    "body": "## Documentation Updates\n\nUpdate migration documentation to reflect revised authentication timing, WebSocket protocol, data validation gates, and rollback triggers.\n\n## Documentation Updates Required\n\n### UNIFIED_MIGRATION_PLAN.md\n- [ ] Add revised authentication migration sequence\n- [ ] Include WebSocket bridge protocol details\n- [ ] Add data validation gates to migration phases\n- [ ] Update rollback triggers and procedures\n- [ ] Include Phase 2.5 in timeline\n- [ ] Add missing endpoints to migration table\n- [ ] Add missing dependencies to migration plan\n\n### DECISION_LOG.md\n- [ ] Document authentication sequence revision decision\n- [ ] Add WebSocket protocol decision rationale\n- [ ] Include data validation decision impact\n- [ ] Update rollback strategy decisions\n- [ ] Document Phase 2.5 addition rationale\n- [ ] Add missing endpoints decision impact\n- [ ] Include missing dependencies decision rationale\n\n## Acceptance Criteria\n\n- [ ] UNIFIED_MIGRATION_PLAN.md updated with all revisions\n- [ ] DECISION_LOG.md updated with all decision rationale\n- [ ] Authentication sequence clearly documented\n- [ ] WebSocket protocol detailed\n- [ ] Data validation gates specified\n- [ ] Rollback triggers documented\n- [ ] Phase 2.5 included in timeline\n- [ ] Missing endpoints added to plan\n- [ ] Missing dependencies added to plan",
    "labels": ["docs", "task"],
    "milestone": 1
  }'

# Issue 7: CI Contract Tests
curl -X POST \
  -H "Accept: application/vnd.github.v3+json" \
  -H "Authorization: token YOUR_GITHUB_TOKEN" \
  https://api.github.com/repos/mandolon/rehome-app/issues \
  -d '{
    "title": "[ci] Contract tests + OpenAPI drift check",
    "body": "## CI Contract Tests and OpenAPI Validation\n\nImplement contract testing and OpenAPI drift detection to ensure API consistency during migration.\n\n## Implementation Requirements\n\n### OpenAPI Specification\n- [ ] Create comprehensive `openapi.yaml` specification\n- [ ] Document all API endpoints (Express and Laravel)\n- [ ] Include request/response schemas\n- [ ] Add authentication requirements\n- [ ] Document error response formats\n\n### Contract Testing\n- [ ] Implement response validation in CI\n- [ ] Add request schema validation\n- [ ] Create contract tests for all endpoints\n- [ ] Implement API compatibility checks\n- [ ] Add response format validation\n\n### Drift Detection\n- [ ] Implement OpenAPI drift detection\n- [ ] Fail CI on API specification drift\n- [ ] Add automated API documentation updates\n- [ ] Create API versioning strategy\n- [ ] Implement backward compatibility checks\n\n## Acceptance Criteria\n\n- [ ] OpenAPI specification created and complete\n- [ ] All endpoints documented in OpenAPI\n- [ ] Contract tests implemented\n- [ ] Response validation working in CI\n- [ ] Drift detection implemented\n- [ ] CI fails on API drift\n- [ ] API documentation automated\n- [ ] Versioning strategy defined\n- [ ] Backward compatibility validated",
    "labels": ["ci", "backend"],
    "milestone": 1
  }'
