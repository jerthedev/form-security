# Research/Audit Sprint Template

**Sprint ID**: [Number]-research-audit-[epic-name]  
**Date Created**: [YYYY-MM-DD]  
**Sprint Duration**: [Start Date] - [End Date]  
**Status**: Not Started  
**Total Points**: 0  
**Sprint Type**: Research/Audit

## Sprint Goal
[Clear statement describing the research and audit objectives for this Epic. Should focus on understanding current state, researching solutions, and planning implementation approach.]

## Sprint Overview
[Detailed description of the research sprint including:]
- Epic being researched and analyzed
- Key research questions to be answered
- Current state analysis requirements
- Technology and best practices to investigate
- Architecture and design decisions to be made
- Implementation planning deliverables expected

## Target Epic
- **Epic ID**: [Epic ID]
- **Epic Title**: [Epic Title]
- **Epic File**: [Path to Epic file]

## Related Documentation
### Specifications
- [ ] [Spec ID] - [Spec Title] - [Path to Spec file]

### Dependencies
- [ ] [Previous research or external dependency]
- [ ] [Additional dependencies]

## Research Tasks
*Tasks loaded from: docs/Planning/Tickets/[Epic-Name]/Research-Audit/*

| Status | Task | File Path | Points | Research Focus | Notes |
|--------|------|-----------|--------|----------------|-------|
| [ ] | [Research Task 1] | [Path to Research-Audit ticket] | [Points] | [Current State/Technology/Architecture] | [Optional notes] |
| [ ] | [Research Task 2] | [Path to Research-Audit ticket] | [Points] | [Current State/Technology/Architecture] | [Optional notes] |
| [ ] | [Research Task 3] | [Path to Research-Audit ticket] | [Points] | [Current State/Technology/Architecture] | [Optional notes] |

**Total Sprint Points**: [Sum of all task points]

## Research Deliverables
### Current State Analysis
- [ ] Existing codebase analysis completed
- [ ] Current architecture documented
- [ ] Gaps and limitations identified
- [ ] Integration points mapped

### Technology Research
- [ ] Best practices research completed
- [ ] Library and framework options evaluated
- [ ] Performance considerations documented
- [ ] Security implications analyzed

### Architecture Planning
- [ ] Technical approach designed
- [ ] Component architecture planned
- [ ] Database schema changes identified
- [ ] API design completed

### Implementation Planning
- [ ] Implementation tickets created for next phase
- [ ] Test Implementation tickets created
- [ ] Code Cleanup tickets created (if needed)
- [ ] Ticket dependencies and sequencing planned

## Ticket Generation Progress
### Implementation Phase Tickets
- [ ] [Ticket ID] - [Ticket Title] - [Status: Created/Planned]
- [ ] [Ticket ID] - [Ticket Title] - [Status: Created/Planned]

### Test Implementation Phase Tickets  
- [ ] [Ticket ID] - [Ticket Title] - [Status: Created/Planned]
- [ ] [Ticket ID] - [Ticket Title] - [Status: Created/Planned]

### Code Cleanup Phase Tickets (if needed)
- [ ] [Ticket ID] - [Ticket Title] - [Status: Created/Planned]

## Research Findings & Decisions
### Key Findings
- [Finding 1]: [Description and implications]
- [Finding 2]: [Description and implications]

### Architecture Decisions
- [Decision 1]: [What was decided and why]
- [Decision 2]: [What was decided and why]

### Technology Choices
- [Choice 1]: [Selected technology/approach and rationale]
- [Choice 2]: [Selected technology/approach and rationale]

## Success Criteria
- [ ] All research tasks completed and validated
- [ ] Current state thoroughly analyzed and documented
- [ ] Technology research completed with clear recommendations
- [ ] Architecture and design decisions made and documented
- [ ] All Implementation phase tickets created and planned
- [ ] All Test Implementation phase tickets created and planned
- [ ] Code Cleanup tickets created (if needed)
- [ ] Implementation roadmap and sequencing completed
- [ ] Research findings documented and reviewed

## AI Prompts

### 1. Next Research Task Determination Prompt
```
You are a Laravel package development expert conducting research for the JTD-FormSecurity package.

TASK: Determine the next research task to work on in the current Research/Audit sprint.

INSTRUCTIONS:
1. Read the Research/Audit Sprint file at: docs/Planning/Sprints/[SPRINT_NUMBER]-research-audit-[EPIC_NAME].md
2. Review the Target Epic file to understand the research scope
3. Analyze the Research Tasks table to identify:
   - Tasks marked as complete [x]
   - Tasks currently in progress [/]
   - Tasks not yet started [ ]
4. Determine the next logical research task based on:
   - Research dependencies (Current State → Technology → Architecture → Planning)
   - Epic complexity and requirements
   - Information needed for subsequent research tasks
5. BEFORE starting work:
   - Update the task status to [/] in the Research Tasks table in the Sprint file
   - Use add_tasks tool to create a detailed breakdown of the research work
   - Use update_tasks tool to track progress as you work
6. Once you identify the next research task:
   - Open the specific Research-Audit ticket file listed in the File Path column
   - Read the AI Prompt section in that ticket file
   - Begin working on that research task following the ticket's instructions
   - Focus on gathering information, analyzing current state, and planning solutions
   - Document ALL findings in a "Research Findings & Analysis" section at the end of the ticket file

RESEARCH CONTEXT:
- Sprint Goal: [SPRINT_GOAL]
- Target Epic: [EPIC_ID] - [EPIC_TITLE]
- Current Sprint: [SPRINT_NUMBER]-research-audit-[EPIC_NAME]
- Sprint File Path: docs/Planning/Sprints/[SPRINT_NUMBER]-research-audit-[EPIC_NAME].md

Please start by reading the sprint file and determining the next research task to work on.
```

### 2. Research Task Completion Validation Prompt
```
You are a Laravel package development expert responsible for validating research task completion in the JTD-FormSecurity package.

TASK: Validate that the most recently worked on research task has been completed successfully.

INSTRUCTIONS:
1. Read the Research/Audit Sprint file at: docs/Planning/Sprints/[SPRINT_NUMBER]-research-audit-[EPIC_NAME].md
2. Identify the last research task that was marked as in progress [/] or recently completed
3. Open the specific Research-Audit ticket file for that task
4. Review the Acceptance Criteria section in the ticket file
5. CRITICAL: Check for "Research Findings & Analysis" section at the end of the ticket file
6. For each acceptance criterion:
   - Verify research has been completed thoroughly
   - Check that findings are documented in the "Research Findings & Analysis" section
   - Validate that deliverables meet research quality standards
   - Ensure any decisions or recommendations are well-supported
   - Mark completed criteria with [x] in the ticket file
7. If ALL acceptance criteria are met AND Research Findings & Analysis section exists:
   - Mark the research task as Complete [x] in the Research Tasks table
   - Update the ticket status to "Complete"
   - Update relevant sections in Research Findings & Decisions in the Sprint file
   - Use update_tasks tool to mark any related task list items as complete
8. If any criteria are not met or Research Findings & Analysis is missing:
   - Keep the task as in progress [/]
   - Document what additional research is needed
   - Provide specific guidance on completing the research

VALIDATION CHECKLIST:
- [ ] All acceptance criteria reviewed and validated
- [ ] "Research Findings & Analysis" section exists and is comprehensive
- [ ] Research findings properly documented in ticket file
- [ ] Decisions and recommendations well-supported
- [ ] Deliverables meet quality standards
- [ ] Information sufficient for implementation planning

RESEARCH CONTEXT:
- Sprint Goal: [SPRINT_GOAL]
- Target Epic: [EPIC_ID] - [EPIC_TITLE]
- Current Sprint: [SPRINT_NUMBER]-research-audit-[EPIC_NAME]
- Sprint File Path: docs/Planning/Sprints/[SPRINT_NUMBER]-research-audit-[EPIC_NAME].md

Please start by reading the sprint file and identifying the research task to validate.
```

### 3. Research Sprint Completion & Ticket Generation Prompt
```
You are a Laravel package development expert responsible for completing research sprints and generating implementation tickets for the JTD-FormSecurity package.

TASK: Validate research sprint completion and generate all necessary tickets for subsequent phases.

INSTRUCTIONS:
1. Read the Research/Audit Sprint file at: docs/Planning/Sprints/[SPRINT_NUMBER]-research-audit-[EPIC_NAME].md
2. Verify Research Completion:
   - Confirm all research tasks are marked as complete [x]
   - Verify all acceptance criteria have been met
   - Check that research findings are thoroughly documented
   - Validate that architecture and technology decisions are made
3. Generate Implementation Phase Tickets:
   - Based on research findings, create detailed Implementation tickets
   - Use the ticket template at docs/Planning/Tickets/template.md
   - Save tickets to: docs/Planning/Tickets/[Epic-Name]/Implementation/
   - Include specific technical requirements from research
   - Reference research findings and architectural decisions
4. Generate Test Implementation Phase Tickets:
   - Create comprehensive test tickets for each implementation ticket
   - Save tickets to: docs/Planning/Tickets/[Epic-Name]/Test-Implementation/
   - Include PHPUnit group definitions following project guidelines
   - Specify coverage requirements and test scenarios
5. Generate Code Cleanup Phase Tickets (if needed):
   - Create cleanup tickets for refactoring or optimization
   - Save tickets to: docs/Planning/Tickets/[Epic-Name]/Code-Cleanup/
6. Update Sprint Documentation:
   - Update Ticket Generation Progress section
   - Document key research findings and decisions
   - Mark sprint status as "Complete"
   - Prepare summary for next sprint planning

TICKET GENERATION REQUIREMENTS:
- [ ] All Implementation tickets created with detailed requirements
- [ ] All Test Implementation tickets created with PHPUnit groups
- [ ] Code Cleanup tickets created (if needed)
- [ ] Ticket dependencies and sequencing documented
- [ ] All tickets reference research findings appropriately
- [ ] Ticket numbering follows project conventions

RESEARCH CONTEXT:
- Sprint Goal: [SPRINT_GOAL]
- Target Epic: [EPIC_ID] - [EPIC_TITLE]
- Current Sprint: [SPRINT_NUMBER]-research-audit-[EPIC_NAME]
- Sprint File Path: docs/Planning/Sprints/[SPRINT_NUMBER]-research-audit-[EPIC_NAME].md

Please start by reading the sprint file and beginning the comprehensive validation and ticket generation process.
```

## Notes
[Any additional context, research findings, or important considerations specific to this research sprint]

## Sprint Completion Checklist
- [ ] All research tasks completed and validated
- [ ] Current state analysis thoroughly documented
- [ ] Technology research completed with clear recommendations
- [ ] Architecture and design decisions made and documented
- [ ] All Implementation phase tickets created
- [ ] All Test Implementation phase tickets created
- [ ] Code Cleanup tickets created (if needed)
- [ ] Research findings and decisions documented
- [ ] Implementation roadmap completed
- [ ] Sprint retrospective completed
