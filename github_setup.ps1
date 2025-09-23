# GitHub API PowerShell Script for Backend Migration Issues
# Execute these commands using PowerShell with GitHub API

# =============================================================================
# SETUP: Replace YOUR_GITHUB_TOKEN with your actual GitHub token
# =============================================================================
$GITHUB_TOKEN = "YOUR_GITHUB_TOKEN"
$REPO = "mandolon/rehome-app"
$HEADERS = @{
    "Accept" = "application/vnd.github.v3+json"
    "Authorization" = "token $GITHUB_TOKEN"
}

# =============================================================================
# 1. CREATE LABELS
# =============================================================================

Write-Host "Creating GitHub labels..." -ForegroundColor Green

# Backend label
Invoke-RestMethod -Uri "https://api.github.com/repos/$REPO/labels" -Method POST -Headers $HEADERS -Body (@{
    name = "backend"
    color = "0e8a16"
    description = "Backend-related issues and tasks"
} | ConvertTo-Json)

# Frontend label
Invoke-RestMethod -Uri "https://api.github.com/repos/$REPO/labels" -Method POST -Headers $HEADERS -Body (@{
    name = "frontend"
    color = "1d76db"
    description = "Frontend-related issues and tasks"
} | ConvertTo-Json)

# Auth label
Invoke-RestMethod -Uri "https://api.github.com/repos/$REPO/labels" -Method POST -Headers $HEADERS -Body (@{
    name = "auth"
    color = "d93f0b"
    description = "Authentication and authorization issues"
} | ConvertTo-Json)

# Sockets label
Invoke-RestMethod -Uri "https://api.github.com/repos/$REPO/labels" -Method POST -Headers $HEADERS -Body (@{
    name = "sockets"
    color = "f9d0c4"
    description = "WebSocket and real-time communication issues"
} | ConvertTo-Json)

# Docs label
Invoke-RestMethod -Uri "https://api.github.com/repos/$REPO/labels" -Method POST -Headers $HEADERS -Body (@{
    name = "docs"
    color = "0075ca"
    description = "Documentation updates and improvements"
} | ConvertTo-Json)

# Task label
Invoke-RestMethod -Uri "https://api.github.com/repos/$REPO/labels" -Method POST -Headers $HEADERS -Body (@{
    name = "task"
    color = "7057ff"
    description = "General development tasks"
} | ConvertTo-Json)

# Bug label
Invoke-RestMethod -Uri "https://api.github.com/repos/$REPO/labels" -Method POST -Headers $HEADERS -Body (@{
    name = "bug"
    color = "d73a4a"
    description = "Something is not working"
} | ConvertTo-Json)

# High Priority label
Invoke-RestMethod -Uri "https://api.github.com/repos/$REPO/labels" -Method POST -Headers $HEADERS -Body (@{
    name = "high-priority"
    color = "b60205"
    description = "High priority issues requiring immediate attention"
} | ConvertTo-Json)

# Phase 2.5 label
Invoke-RestMethod -Uri "https://api.github.com/repos/$REPO/labels" -Method POST -Headers $HEADERS -Body (@{
    name = "phase-2.5"
    color = "ff6b35"
    description = "Phase 2.5 TODO implementation tasks"
} | ConvertTo-Json)

# Data Validation label
Invoke-RestMethod -Uri "https://api.github.com/repos/$REPO/labels" -Method POST -Headers $HEADERS -Body (@{
    name = "data-validation"
    color = "0e8a16"
    description = "Data validation and integrity checks"
} | ConvertTo-Json)

# CI label
Invoke-RestMethod -Uri "https://api.github.com/repos/$REPO/labels" -Method POST -Headers $HEADERS -Body (@{
    name = "ci"
    color = "1d76db"
    description = "Continuous integration and testing"
} | ConvertTo-Json)

Write-Host "Labels created successfully!" -ForegroundColor Green

# =============================================================================
# 2. CREATE MILESTONE
# =============================================================================

Write-Host "Creating milestone..." -ForegroundColor Green

$milestone = Invoke-RestMethod -Uri "https://api.github.com/repos/$REPO/milestones" -Method POST -Headers $HEADERS -Body (@{
    title = "Backend Consolidation v1 (12–14 weeks)"
    description = "Migration to Laravel primary backend with Express WS bridge; includes Phase 2.5 TODOs, missing endpoints, auth cutover, WS protocol, data validation, CI contract tests."
    state = "open"
    due_on = "2025-03-19T00:00:00Z"
} | ConvertTo-Json)

$milestoneNumber = $milestone.number
Write-Host "Milestone created with number: $milestoneNumber" -ForegroundColor Green

# =============================================================================
# 3. CREATE ISSUES
# =============================================================================

Write-Host "Creating issues..." -ForegroundColor Green

# Issue 1: Missing Endpoints
$issue1 = Invoke-RestMethod -Uri "https://api.github.com/repos/$REPO/issues" -Method POST -Headers $HEADERS -Body (@{
    title = "[high-priority][backend] Implement 8 missing endpoints in Laravel"
    body = @"
## Missing Endpoints Implementation

Add the following endpoints to Laravel backend:

- `GET /api/tasks/all`
- `DELETE /api/tasks/:id/permanent`
- `POST /api/tasks/:id/restore`
- `PATCH /api/tasks/:id/status`
- `POST /api/tasks/:id/archive`
- `PUT /api/tasks/:id/messages/:messageId`
- `DELETE /api/tasks/:id/messages/:messageId`
- `GET /api/projects/:id/tasks`

## Requirements

- **Policies**: Implement role-based access control (Admin/Team/Client)
- **Tests**: Add Pest feature tests for all endpoints
- **API Resources**: Use Laravel API Resources for consistent response format
- **Pagination**: Implement pagination for list endpoints
- **Error Format**: Standardize error response format
- **OpenAPI**: Update OpenAPI documentation

## Acceptance Criteria

- [ ] All 8 endpoints implemented in Laravel
- [ ] Role-based policies implemented
- [ ] Pest feature tests passing
- [ ] API Resources used for responses
- [ ] Pagination implemented
- [ ] Error format standardized
- [ ] OpenAPI documentation updated
"@
    labels = @("backend", "high-priority")
    milestone = $milestoneNumber
} | ConvertTo-Json)

Write-Host "Issue 1 created: $($issue1.number)" -ForegroundColor Yellow

# Issue 2: Authentication Migration
$issue2 = Invoke-RestMethod -Uri "https://api.github.com/repos/$REPO/issues" -Method POST -Headers $HEADERS -Body (@{
    title = "[auth][high-priority] Fix authentication migration sequence (dual-stack → cutover)"
    body = @"
## Authentication Migration Sequence Fix

Current plan has logical flaw: migrates authentication system BEFORE frontend API calls are updated, causing users to be logged out.

## Solution

Introduce feature flags and implement proper migration sequence:

1. **Keep Express sessions** while frontend switches to Laravel endpoints
2. **Implement dual authentication** (Express sessions + Laravel tokens)
3. **Only then enable Sanctum-only** authentication
4. **Add rollback steps** for authentication failures
5. **Preserve user sessions** during transition

## Implementation Steps

- [ ] Implement feature flags for authentication system
- [ ] Create dual authentication bridge
- [ ] Update frontend to use Laravel endpoints with Express sessions
- [ ] Implement session migration scripts
- [ ] Add rollback procedures
- [ ] Test authentication flow in both systems
- [ ] Create user migration procedures

## Acceptance Criteria

- [ ] Feature flags implemented
- [ ] Dual authentication working
- [ ] Frontend uses Laravel endpoints with Express sessions
- [ ] Session migration scripts created
- [ ] Rollback procedures documented
- [ ] Authentication flow tested
- [ ] User migration procedures created
"@
    labels = @("auth", "high-priority")
    milestone = $milestoneNumber
} | ConvertTo-Json)

Write-Host "Issue 2 created: $($issue2.number)" -ForegroundColor Yellow

# Issue 3: WebSocket Bridge Protocol
$issue3 = Invoke-RestMethod -Uri "https://api.github.com/repos/$REPO/issues" -Method POST -Headers $HEADERS -Body (@{
    title = "[sockets][high-priority] Define WS bridge protocol (Laravel → Redis → Express ws)"
    body = @"
## WebSocket Bridge Protocol Implementation

Define and implement inter-service communication protocol between Laravel and Express WebSocket server.

## Protocol Specification

### Event Schema v1
```json
{
  "type": "task_created|task_updated|task_deleted",
  "projectId": "string",
  "entity": "task|project|user",
  "payload": {},
  "timestamp": "ISO8601",
  "signature": "string"
}
```

### Communication Flow
1. **Laravel broadcasts** to Redis channel
2. **Express WS** subscribes to Redis channel
3. **Express WS relays** to connected clients
4. **Authentication** for private channels
5. **Health checks** and monitoring
6. **Backoff & replay** mechanisms

## Implementation Requirements

- [ ] Define event schema v1
- [ ] Implement Laravel Redis broadcasting
- [ ] Create Express Redis subscriber
- [ ] Implement WebSocket relay logic
- [ ] Add authentication for private channels
- [ ] Implement health checks
- [ ] Add backoff and replay mechanisms
- [ ] Create monitoring and alerting

## Acceptance Criteria

- [ ] Event schema defined and documented
- [ ] Laravel Redis broadcasting working
- [ ] Express Redis subscriber implemented
- [ ] WebSocket relay functional
- [ ] Private channel authentication working
- [ ] Health checks implemented
- [ ] Backoff and replay mechanisms working
- [ ] Monitoring and alerting setup
"@
    labels = @("sockets", "high-priority")
    milestone = $milestoneNumber
} | ConvertTo-Json)

Write-Host "Issue 3 created: $($issue3.number)" -ForegroundColor Yellow

# Issue 4: Phase 2.5 TODOs
$issue4 = Invoke-RestMethod -Uri "https://api.github.com/repos/$REPO/issues" -Method POST -Headers $HEADERS -Body (@{
    title = "[phase-2.5] Resolve TODO/FIXME pre-migration"
    body = @"
## Phase 2.5: Resolve Outstanding TODOs

Implement missing features identified in codebase before migration to prevent complications.

## TODO/FIXME Items to Implement

### Project Operations
- `SidebarProjectSection.tsx:203` - "TODO: Implement rename functionality"
- `SidebarProjectSection.tsx:206` - "TODO: Implement duplicate functionality"
- `SidebarProjectSection.tsx:209` - "TODO: Implement archive functionality"

### Whiteboard Operations
- `WhiteboardsHeader.tsx:33` - "TODO: Implement create whiteboard functionality"

## Implementation Requirements

- [ ] Implement project rename functionality in both systems
- [ ] Implement project duplicate functionality in both systems
- [ ] Implement project archive functionality in both systems
- [ ] Implement whiteboard creation in both systems
- [ ] Add comprehensive tests for all new features
- [ ] Update UI to use new endpoints
- [ ] Ensure feature parity between Express and Laravel

## Acceptance Criteria

- [ ] Project rename functionality working
- [ ] Project duplicate functionality working
- [ ] Project archive functionality working
- [ ] Whiteboard creation functionality working
- [ ] Tests added and passing
- [ ] UI updated to use new endpoints
- [ ] Feature parity validated between systems
"@
    labels = @("phase-2.5", "task")
    milestone = $milestoneNumber
} | ConvertTo-Json)

Write-Host "Issue 4 created: $($issue4.number)" -ForegroundColor Yellow

# Issue 5: Data Validation
$issue5 = Invoke-RestMethod -Uri "https://api.github.com/repos/$REPO/issues" -Method POST -Headers $HEADERS -Body (@{
    title = "[data-validation] Drizzle↔Eloquent data validation scripts"
    body = @"
## Data Validation Scripts

Create comprehensive data validation scripts to ensure data integrity during migration from Drizzle ORM to Eloquent ORM.

## Validation Requirements

### Per-Table Checks
- **Data Types**: Verify all data types map correctly
- **JSONB Shape**: Validate complex JSONB fields
- **Soft Deletes**: Check soft delete timestamps and metadata
- **Foreign Keys**: Validate relationship constraints
- **Indexes**: Ensure database indexes work correctly
- **Data Encryption**: Test encrypted fields migration
- **Data Compression**: Test compressed data fields

## Implementation

- [ ] Generate per-table validation scripts
- [ ] Create data type compatibility checks
- [ ] Implement JSONB field validation
- [ ] Add soft delete validation
- [ ] Create foreign key constraint checks
- [ ] Implement index validation
- [ ] Add data encryption validation
- [ ] Create dry-run reports
- [ ] Implement CI gate on validation failures

## Acceptance Criteria

- [ ] Per-table validation scripts created
- [ ] Data type compatibility verified
- [ ] JSONB fields validated
- [ ] Soft deletes validated
- [ ] Foreign keys validated
- [ ] Indexes validated
- [ ] Encryption validated
- [ ] Dry-run reports generated
- [ ] CI gate implemented
"@
    labels = @("data-validation", "backend")
    milestone = $milestoneNumber
} | ConvertTo-Json)

Write-Host "Issue 5 created: $($issue5.number)" -ForegroundColor Yellow

# Issue 6: Documentation Updates
$issue6 = Invoke-RestMethod -Uri "https://api.github.com/repos/$REPO/issues" -Method POST -Headers $HEADERS -Body (@{
    title = "[docs] Update UNIFIED_MIGRATION_PLAN + DECISION_LOG"
    body = @"
## Documentation Updates

Update migration documentation to reflect revised authentication timing, WebSocket protocol, data validation gates, and rollback triggers.

## Documentation Updates Required

### UNIFIED_MIGRATION_PLAN.md
- [ ] Add revised authentication migration sequence
- [ ] Include WebSocket bridge protocol details
- [ ] Add data validation gates to migration phases
- [ ] Update rollback triggers and procedures
- [ ] Include Phase 2.5 in timeline
- [ ] Add missing endpoints to migration table
- [ ] Add missing dependencies to migration plan

### DECISION_LOG.md
- [ ] Document authentication sequence revision decision
- [ ] Add WebSocket protocol decision rationale
- [ ] Include data validation decision impact
- [ ] Update rollback strategy decisions
- [ ] Document Phase 2.5 addition rationale
- [ ] Add missing endpoints decision impact
- [ ] Include missing dependencies decision rationale

## Acceptance Criteria

- [ ] UNIFIED_MIGRATION_PLAN.md updated with all revisions
- [ ] DECISION_LOG.md updated with all decision rationale
- [ ] Authentication sequence clearly documented
- [ ] WebSocket protocol detailed
- [ ] Data validation gates specified
- [ ] Rollback triggers documented
- [ ] Phase 2.5 included in timeline
- [ ] Missing endpoints added to plan
- [ ] Missing dependencies added to plan
"@
    labels = @("docs", "task")
    milestone = $milestoneNumber
} | ConvertTo-Json)

Write-Host "Issue 6 created: $($issue6.number)" -ForegroundColor Yellow

# Issue 7: CI Contract Tests
$issue7 = Invoke-RestMethod -Uri "https://api.github.com/repos/$REPO/issues" -Method POST -Headers $HEADERS -Body (@{
    title = "[ci] Contract tests + OpenAPI drift check"
    body = @"
## CI Contract Tests and OpenAPI Validation

Implement contract testing and OpenAPI drift detection to ensure API consistency during migration.

## Implementation Requirements

### OpenAPI Specification
- [ ] Create comprehensive `openapi.yaml` specification
- [ ] Document all API endpoints (Express and Laravel)
- [ ] Include request/response schemas
- [ ] Add authentication requirements
- [ ] Document error response formats

### Contract Testing
- [ ] Implement response validation in CI
- [ ] Add request schema validation
- [ ] Create contract tests for all endpoints
- [ ] Implement API compatibility checks
- [ ] Add response format validation

### Drift Detection
- [ ] Implement OpenAPI drift detection
- [ ] Fail CI on API specification drift
- [ ] Add automated API documentation updates
- [ ] Create API versioning strategy
- [ ] Implement backward compatibility checks

## Acceptance Criteria

- [ ] OpenAPI specification created and complete
- [ ] All endpoints documented in OpenAPI
- [ ] Contract tests implemented
- [ ] Response validation working in CI
- [ ] Drift detection implemented
- [ ] CI fails on API drift
- [ ] API documentation automated
- [ ] Versioning strategy defined
- [ ] Backward compatibility validated
"@
    labels = @("ci", "backend")
    milestone = $milestoneNumber
} | ConvertTo-Json)

Write-Host "Issue 7 created: $($issue7.number)" -ForegroundColor Yellow

Write-Host "All GitHub setup completed successfully!" -ForegroundColor Green
Write-Host "Milestone: Backend Consolidation v1 (12–14 weeks)" -ForegroundColor Cyan
Write-Host "Issues created: 7" -ForegroundColor Cyan
Write-Host "Labels created: 11" -ForegroundColor Cyan
