# Sprint Template

**Sprint ID**: [Number]-[short-description]
**Date Created**: [YYYY-MM-DD]
**Sprint Duration**: [Start Date] - [End Date]
**Status**: Not Started
**Total Points**: 0

## Sprint Goal
[Clear, concise statement describing what this sprint aims to achieve. Should be specific, measurable, and aligned with Epic objectives.]

## Sprint Overview
[Detailed description of the sprint including:]
- Primary objectives and deliverables
- Key features or capabilities being developed
- How this sprint contributes to the overall Epic goals
- Target outcomes and success metrics
- Any specific focus areas or priorities

## Related Documentation
### Epics
- [ ] [Epic ID] - [Epic Title] - [Path to Epic file]

### Specifications
- [ ] [Spec ID] - [Spec Title] - [Path to Spec file]

### Dependencies
- [ ] [Previous Sprint or external dependency]
- [ ] [Additional dependencies]

## Sprint Tasks

| Status | Task | File Path | Phase | Points | Notes |
|--------|------|-----------|-------|--------|-------|
| [ ] | [Task Title 1] | [Path to ticket file] | [Phase] | [Points] | [Optional notes] |
| [ ] | [Task Title 2] | [Path to ticket file] | [Phase] | [Points] | [Optional notes] |
| [ ] | [Task Title 3] | [Path to ticket file] | [Phase] | [Points] | [Optional notes] |

**Total Sprint Points**: [Sum of all task points]

## Testing Strategy
### PHPUnit Test Groups
- **Sprint Group**: `#[Group('sprint-[number]')]`
- **Epic Groups**: `#[Group('epic-[number]')]`
- **Feature Groups**: `#[Group('[feature-name]')]`
- **Ticket Groups**: `#[Group('ticket-[number]')]`

### Test Execution Commands
```bash
# Run all sprint tests
vendor/bin/phpunit --group sprint-[number]

# Run sprint tests with coverage
vendor/bin/phpunit --group sprint-[number] --coverage-html coverage/sprint-[number]

# Run specific phase tests
vendor/bin/phpunit --group sprint-[number],implementation
vendor/bin/phpunit --group sprint-[number],integration
```

## Success Criteria
- [ ] All sprint tasks completed and marked as done
- [ ] All acceptance criteria met for each task
- [ ] PHPUnit test group `sprint-[number]` passes with 100% success rate
- [ ] Code coverage for sprint features meets minimum 80% threshold
- [ ] All tests pass (no regressions introduced)
- [ ] Sprint goal achieved and validated

## Sprint Retrospective
### What Went Well
- [To be filled during/after sprint]

### What Could Be Improved
- [To be filled during/after sprint]

### Action Items for Next Sprint
- [To be filled during/after sprint]

## AI Prompts

### 1. Next Task Determination Prompt
```
You are a Laravel package development expert working on the JTD-FormSecurity package.

TASK: Determine the next task to work on in the current sprint.

INSTRUCTIONS:
1. Read the Sprint file at: docs/Planning/Sprints/[SPRINT_NUMBER]-[SPRINT_DESCRIPTION].md
2. Analyze the Sprint Tasks table to identify:
   - Tasks marked as complete [x]
   - Tasks currently in progress [/]
   - Tasks not yet started [ ]
3. Determine the next logical task based on:
   - Task dependencies and prerequisites
   - Sprint phase progression (Research → Implementation → Testing → Cleanup)
   - Current sprint progress and priorities
4. BEFORE starting work:
   - Update the task status to [/] in the Sprint Tasks table in the Sprint file
   - Use add_tasks tool to create a detailed breakdown of the implementation work
   - Use update_tasks tool to track progress as you work
5. Once you identify the next task:
   - Open the specific ticket file listed in the File Path column
   - Read the AI Prompt section in that ticket file
   - Begin working on that task following the ticket's instructions

SPRINT CONTEXT:
- Sprint Goal: [SPRINT_GOAL]
- Current Sprint: [SPRINT_NUMBER]-[SPRINT_DESCRIPTION]
- Sprint File Path: docs/Planning/Sprints/[SPRINT_NUMBER]-[SPRINT_DESCRIPTION].md

Please start by reading the sprint file and determining the next task to work on.
```

### 2. Task Completion Validation Prompt
```
You are a Laravel package development expert responsible for validating task completion in the JTD-FormSecurity package.

TASK: Validate that the most recently worked on task has been completed successfully.

INSTRUCTIONS:
1. Read the Sprint file at: docs/Planning/Sprints/[SPRINT_NUMBER]-[SPRINT_DESCRIPTION].md
2. Identify the last task that was marked as in progress [/] or recently completed
3. Open the specific ticket file for that task
4. Review the Acceptance Criteria section in the ticket file
5. For each acceptance criterion:
   - Verify if the criterion has been met
   - Check related code, tests, and documentation
   - Run any applicable tests to validate functionality
   - Mark completed criteria with [x] in the ticket file
6. If ALL acceptance criteria are met:
   - Mark the task as Complete [x] in the Sprint Tasks table
   - Update the ticket status to "Complete"
   - Use update_tasks tool to mark any related task list items as complete
7. If any criteria are not met:
   - Keep the task as in progress [/]
   - Document what still needs to be completed
   - Provide specific guidance on remaining work
   - Use update_tasks tool to reflect current progress

VALIDATION CHECKLIST:
- [ ] All acceptance criteria reviewed and validated
- [ ] Related tests pass (if applicable)
- [ ] Code quality meets project standards
- [ ] Documentation updated (if required)
- [ ] Integration with existing codebase verified

SPRINT CONTEXT:
- Sprint Goal: [SPRINT_GOAL]
- Current Sprint: [SPRINT_NUMBER]-[SPRINT_DESCRIPTION]
- Sprint File Path: docs/Planning/Sprints/[SPRINT_NUMBER]-[SPRINT_DESCRIPTION].md

Please start by reading the sprint file and identifying the task to validate.
```

### 3. Sprint Completion Validation Prompt
```
You are a Laravel package development expert responsible for validating sprint completion in the JTD-FormSecurity package.

TASK: Validate that the entire sprint has been completed successfully and meets all quality standards.

INSTRUCTIONS:
1. Read the Sprint file at: docs/Planning/Sprints/[SPRINT_NUMBER]-[SPRINT_DESCRIPTION].md
2. Verify Sprint Task Completion:
   - Confirm all tasks in the Sprint Tasks table are marked as complete [x]
   - Verify each task's acceptance criteria have been met
   - Check that all related ticket files show "Complete" status
3. Validate PHPUnit Test Group:
   - Confirm PHPUnit group `sprint-[number]` has been established
   - Run: `vendor/bin/phpunit --group sprint-[number]`
   - Verify 100% test pass rate for sprint-specific tests
   - Generate coverage report: `vendor/bin/phpunit --group sprint-[number] --coverage-html coverage/sprint-[number]`
   - Confirm minimum 80% code coverage for sprint features
4. Run Full Test Suite:
   - Execute: `vendor/bin/phpunit`
   - Verify no regressions introduced (100% pass rate)
   - Confirm overall package stability maintained
5. Validate Sprint Goal Achievement:
   - Review sprint goal against completed work
   - Confirm all deliverables have been achieved
   - Validate success criteria have been met
6. Final Sprint Status Update:
   - If all validations pass: Mark sprint status as "Complete"
   - If any issues found: Document specific problems and required fixes
   - Update sprint retrospective with findings

QUALITY GATES (ALL MUST PASS):
- [ ] All sprint tasks completed with acceptance criteria met
- [ ] PHPUnit group `sprint-[number]` exists and passes 100%
- [ ] Sprint features have minimum 80% code coverage
- [ ] Full test suite passes without regressions
- [ ] Sprint goal achieved and validated
- [ ] All related documentation updated

SPRINT CONTEXT:
- Sprint Goal: [SPRINT_GOAL]
- Current Sprint: [SPRINT_NUMBER]-[SPRINT_DESCRIPTION]
- Sprint File Path: docs/Planning/Sprints/[SPRINT_NUMBER]-[SPRINT_DESCRIPTION].md

Please start by reading the sprint file and beginning the comprehensive validation process.
```

## Notes
[Any additional context, decisions made, or important considerations specific to this sprint]

## Sprint Completion Checklist
- [ ] All tasks completed and validated
- [ ] All acceptance criteria met
- [ ] PHPUnit test group established and passing
- [ ] Code coverage meets minimum threshold (80%+)
- [ ] Full test suite passes (no regressions)
- [ ] Sprint goal achieved
- [ ] Documentation updated
- [ ] Sprint retrospective completed
