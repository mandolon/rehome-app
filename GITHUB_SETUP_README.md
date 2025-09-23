# GitHub Setup Instructions for Backend Migration

This directory contains scripts to automatically create GitHub labels, milestone, and issues for the backend migration project.

## Prerequisites

1. **GitHub Personal Access Token**
   - Go to GitHub → Settings → Developer settings → Personal access tokens
   - Create a new token with `repo` scope
   - Copy the token for use in scripts

2. **PowerShell or Bash**
   - Windows: Use PowerShell (included with Windows)
   - Mac/Linux: Use Bash terminal

## Quick Setup

### Option 1: PowerShell (Windows)
```powershell
# 1. Edit the script to add your GitHub token
notepad github_setup.ps1

# 2. Replace YOUR_GITHUB_TOKEN with your actual token
# 3. Run the script
.\github_setup.ps1
```

### Option 2: Bash (Mac/Linux)
```bash
# 1. Edit the script to add your GitHub token
nano github_setup_script.sh

# 2. Replace YOUR_GITHUB_TOKEN with your actual token
# 3. Make executable and run
chmod +x github_setup_script.sh
./github_setup_script.sh
```

## What Gets Created

### 🏷️ Labels (11 labels)
- `backend` - Backend-related issues and tasks
- `frontend` - Frontend-related issues and tasks
- `auth` - Authentication and authorization issues
- `sockets` - WebSocket and real-time communication issues
- `docs` - Documentation updates and improvements
- `task` - General development tasks
- `bug` - Something is not working
- `high-priority` - High priority issues requiring immediate attention
- `phase-2.5` - Phase 2.5 TODO implementation tasks
- `data-validation` - Data validation and integrity checks
- `ci` - Continuous integration and testing

### 🎯 Milestone (1 milestone)
- **Backend Consolidation v1 (12–14 weeks)**
  - Due: March 19, 2025
  - Description: Migration to Laravel primary backend with Express WS bridge

### 📋 Issues (7 issues)

#### High Priority Issues
1. **[high-priority][backend] Implement 8 missing endpoints in Laravel**
   - Add 8 missing API endpoints to Laravel backend
   - Include policies, tests, API Resources, pagination, error format

2. **[auth][high-priority] Fix authentication migration sequence**
   - Fix logical flaw in auth migration timing
   - Implement dual-stack authentication with feature flags

3. **[sockets][high-priority] Define WS bridge protocol**
   - Laravel → Redis → Express WebSocket communication
   - Event schema, authentication, health checks, monitoring

#### Medium Priority Issues
4. **[phase-2.5] Resolve TODO/FIXME pre-migration**
   - Implement project rename/duplicate/archive functionality
   - Implement whiteboard creation functionality

5. **[data-validation] Drizzle↔Eloquent data validation scripts**
   - Per-table validation for data types, JSONB, soft deletes
   - CI gate on validation failures

6. **[docs] Update UNIFIED_MIGRATION_PLAN + DECISION_LOG**
   - Update documentation with revised auth timing
   - Add WebSocket protocol, data validation gates

7. **[ci] Contract tests + OpenAPI drift check**
   - OpenAPI specification and contract testing
   - CI validation and drift detection

## Manual Setup (Alternative)

If you prefer to create these manually through GitHub UI:

### 1. Create Labels
Go to: `https://github.com/mandolon/rehome-app/labels/new`

Create each label with the specified color and description.

### 2. Create Milestone
Go to: `https://github.com/mandolon/rehome-app/milestones/new`

- Title: `Backend Consolidation v1 (12–14 weeks)`
- Description: `Migration to Laravel primary backend with Express WS bridge; includes Phase 2.5 TODOs, missing endpoints, auth cutover, WS protocol, data validation, CI contract tests.`
- Due date: `March 19, 2025`

### 3. Create Issues
Go to: `https://github.com/mandolon/rehome-app/issues/new`

Create each issue with the title, body, labels, and milestone as specified in the scripts.

## Verification

After running the scripts, verify the setup:

1. **Check Labels**: Go to `https://github.com/mandolon/rehome-app/labels`
2. **Check Milestone**: Go to `https://github.com/mandolon/rehome-app/milestones`
3. **Check Issues**: Go to `https://github.com/mandolon/rehome-app/issues`

## Troubleshooting

### Common Issues

1. **Authentication Error**
   - Ensure your GitHub token has `repo` scope
   - Check that the token is correctly set in the script

2. **Permission Denied**
   - Ensure you have write access to the `mandolon/rehome-app` repository
   - Check that the repository exists and is accessible

3. **Script Execution Error**
   - Windows: Ensure PowerShell execution policy allows scripts
   - Mac/Linux: Ensure the script has execute permissions

### PowerShell Execution Policy (Windows)
```powershell
# Check current policy
Get-ExecutionPolicy

# If restricted, allow local scripts
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
```

## Next Steps

After successful setup:

1. **Assign Issues**: Assign issues to team members
2. **Set Priorities**: Use GitHub's priority labels
3. **Create Project Board**: Organize issues in a project board
4. **Set Up CI**: Implement the CI contract tests
5. **Begin Implementation**: Start with high-priority issues

## Support

If you encounter issues:

1. Check the GitHub API documentation
2. Verify your token permissions
3. Check repository access
4. Review the script logs for specific error messages

## Files Created

- `github_setup.ps1` - PowerShell script for Windows
- `github_setup_script.sh` - Bash script for Mac/Linux
- `README.md` - This instruction file

All scripts are ready to use and will create the complete GitHub setup for the backend migration project.
