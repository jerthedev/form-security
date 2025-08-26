# Ticket Template

**Ticket ID**: [Phase]/[Number]-[short-description]  
**Date Created**: [YYYY-MM-DD]  
**Status**: Not Started

## Title
[Clear, concise title describing what needs to be done]

## Description
[Comprehensive details about the task, including:]
- What needs to be accomplished
- Why this work is necessary
- Current state vs desired state
- Any specific requirements or constraints
- Dependencies on other tickets or systems
- Expected outcomes and success criteria

## Related Documentation
- [ ] [Document 1] - [Brief description of relevance]
- [ ] [Document 2] - [Brief description of relevance]
- [ ] [Additional specs, architecture docs, etc.]

## Related Files
- [ ] [File path 1] - [What needs to be done with this file]
- [ ] [File path 2] - [What needs to be done with this file]
- [ ] [Additional source files, configs, etc.]

## Related Tests
- [ ] [Test file 1] - [What needs to be tested/verified]
- [ ] [Test file 2] - [What needs to be tested/verified]
- [ ] [Additional test files, test scenarios, etc.]

## Acceptance Criteria
- [ ] [Specific, measurable criterion 1]
- [ ] [Specific, measurable criterion 2]
- [ ] [Additional criteria that define "done"]

## AI Prompt
```
You are a Laravel package development expert working on the JTD-FormSecurity package.

TASK: Complete this ticket following the acceptance criteria and requirements.

INSTRUCTIONS:
1. Read this ticket fully: [TICKET_PATH]
2. Use add_tasks tool to create a detailed breakdown of all work needed
3. Use update_tasks tool to track progress as you work
4. For Research/Audit tickets: Document findings in "Research Findings & Analysis" section at end of ticket
5. For Implementation tickets: Follow Laravel 12 and PHP 8.2+ best practices
6. For Test tickets: Use PHPUnit 12 with appropriate group attributes
7. Update acceptance criteria with [x] as you complete each item
8. Update ticket status to "Complete" when all criteria are met

REQUIREMENTS:
- Follow project guidelines in docs/project-guidelines.txt
- Use task management tools for work breakdown and progress tracking
- Document all findings and decisions appropriately
- Ensure code quality meets project standards
- Run tests and validate functionality before marking complete

Please start by reading the ticket and creating your task breakdown.
```

## Phase Descriptions
- Research/Audit: 
  - Gather requirements
  - Search latest information about APIs and development practices including how other developers have solved similar problems using Brave Search MCP
  - Analyze existing code, plan implementation
  - Generate tickets for Implementation, Test Implementation, and Code Cleanup based on findings
- Implementation: Develop new features, update documentation
- Test Implementation: Write tests, verify functionality, performance, security
- Code Cleanup: Refactor, optimize, remove technical debt (optional, only if needed)

## Notes
[Any additional context, decisions made, or important considerations]

## Estimated Effort
[Time estimate: Small (< 4 hours), Medium (4-8 hours), Large (1-2 days), XL (2+ days)]

## Dependencies
- [ ] [Ticket ID or external dependency]
- [ ] [Additional dependencies]
