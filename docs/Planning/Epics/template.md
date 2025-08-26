# Epic Template

**Epic ID**: [Major Feature Number]-[short-description]  
**Date Created**: [YYYY-MM-DD]  
**Status**: Not Started  
**Priority**: [High/Medium/Low]

## Title
[Clear, concise title describing the major feature or capability being developed]

## Epic Overview
[High-level description of the major feature, including:]
- What major capability or feature this Epic delivers
- Why this Epic is important to the overall package goals
- How this Epic fits into the broader JTD-FormSecurity package vision
- Target users and use cases this Epic addresses
- Key business or technical value this Epic provides

## Epic Goals & Objectives
- [ ] [Primary goal 1 - what major outcome this Epic achieves]
- [ ] [Primary goal 2 - additional key outcome]
- [ ] [Secondary goal 1 - supporting objective]
- [ ] [Additional goals as needed]

## Scope & Boundaries
### In Scope
- [Feature/capability 1 that is included in this Epic]
- [Feature/capability 2 that is included in this Epic]
- [Additional items clearly within this Epic's boundaries]

### Out of Scope
- [Feature/capability 1 that is explicitly NOT included]
- [Feature/capability 2 that will be handled in future Epics]
- [Additional items that might be confused as part of this Epic]

## User Stories & Use Cases
### Primary User Stories
1. **As a [user type]**, I want [capability] so that [benefit/outcome]
2. **As a [user type]**, I want [capability] so that [benefit/outcome]
3. [Additional primary user stories]

### Secondary User Stories
1. **As a [user type]**, I want [capability] so that [benefit/outcome]
2. [Additional secondary user stories]

### Use Case Scenarios
- **Scenario 1**: [Detailed walkthrough of how users will interact with this feature]
- **Scenario 2**: [Additional realistic usage scenario]
- [Additional scenarios as needed]

## Technical Architecture Overview
[High-level technical approach, including:]
- Key components that will be built or modified
- Integration points with existing JTD-FormSecurity features
- External dependencies or third-party integrations
- Database schema changes or additions
- API endpoints or interfaces that will be created
- Configuration options that will be available

## Success Criteria
### Functional Requirements
- [ ] [Specific functional requirement 1]
- [ ] [Specific functional requirement 2]
- [ ] [Additional functional requirements]

### Non-Functional Requirements
- [ ] [Performance requirement - e.g., response time, throughput]
- [ ] [Security requirement - e.g., data protection, access control]
- [ ] [Usability requirement - e.g., ease of configuration, documentation]
- [ ] [Compatibility requirement - e.g., Laravel versions, PHP versions]
- [ ] [Additional non-functional requirements]

## Dependencies & Prerequisites
### Internal Dependencies
- [ ] [Other JTD-FormSecurity Epic or feature that must be completed first]
- [ ] [Existing package component that must be refactored or updated]
- [ ] [Additional internal dependencies]

### External Dependencies
- [ ] [Third-party package or service required]
- [ ] [Laravel framework version or feature dependency]
- [ ] [PHP version or extension requirement]
- [ ] [Additional external dependencies]

## Risk Assessment
### High Risk Items
- **Risk**: [Description of significant risk]
  - **Impact**: [What happens if this risk occurs]
  - **Mitigation**: [How to prevent or minimize this risk]

### Medium Risk Items
- **Risk**: [Description of moderate risk]
  - **Impact**: [What happens if this risk occurs]
  - **Mitigation**: [How to prevent or minimize this risk]

### Low Risk Items
- [List of minor risks that should be monitored]

## Estimated Effort & Timeline
**Overall Epic Size**: [Small (1-2 weeks), Medium (3-4 weeks), Large (1-2 months), XL (2+ months)]

### Phase Breakdown
- **Research/Audit Phase**: [Time estimate] - [Brief description of research needed]
- **Implementation Phase**: [Time estimate] - [Brief description of development work]
- **Test Implementation Phase**: [Time estimate] - [Brief description of testing work]
- **Code Cleanup Phase**: [Time estimate] - [Brief description of cleanup work]

## Related Documentation
- [ ] [Existing documentation that relates to this Epic]
- [ ] [Specifications or requirements documents]
- [ ] [Architecture documents that need to be updated]
- [ ] [User documentation that will need updates]

## AI Prompt for Research/Audit Ticket Generation
```
You are a Laravel package development expert specializing in form security and spam prevention. You have been assigned to systematically create Research/Audit tickets for this Epic: [EPIC_PATH]

CONTEXT:
- Package: JTD-FormSecurity - A comprehensive Laravel package for form security and spam prevention
- Epic Template: docs/Planning/Epics/template.md
- Ticket Template: docs/Planning/Tickets/template.md
- This Epic: [EPIC_ID] - [EPIC_TITLE]

DIRECTORY STRUCTURE:
- Epic Templates: docs/Planning/Epics/template.md
- Ticket Templates: docs/Planning/Tickets/template.md
- Epic Files: docs/Planning/Epics/[epic-id]-[short-description].md
- Ticket Files: docs/Planning/Tickets/[Epic-Name]/[Phase]/[ticket-number]-[short-description].md
- Phases: Research/Audit, Implementation, Test Implementation, Code Cleanup
- Numbering: Use [Epic Number]000s (e.g., Epic 1 = 1000s, Epic 2 = 2000s, etc.)

TASK:
Please read the complete Epic file at [EPIC_PATH] and analyze:
1. Epic Overview and Goals
2. Scope and Boundaries  
3. User Stories and Use Cases
4. Technical Architecture Overview
5. Success Criteria and Requirements
6. Dependencies and Risk Assessment

Based on this analysis, create a comprehensive set of Research/Audit tickets that will:
1. **Research Current State**: Analyze existing JTD-FormSecurity codebase for relevant components
2. **Technology Research**: Investigate best practices, libraries, and approaches for this Epic's requirements
3. **Architecture Planning**: Design the technical approach and integration strategy
4. **Requirement Analysis**: Break down Epic requirements into implementable features
5. **Dependency Mapping**: Identify all internal and external dependencies
6. **Risk Mitigation Planning**: Create strategies for identified risks
7. **Implementation Planning**: Plan the sequence and structure of Implementation phase tickets

For each Research/Audit ticket:
- Use the ticket template at docs/Planning/Tickets/template.md
- Create detailed, actionable research tasks
- Include specific deliverables and success criteria
- Plan for creation of subsequent Implementation, Test Implementation, and Code Cleanup tickets
- Consider Laravel best practices, security implications, and package architecture

Create tickets in this order:
1. Current State Analysis (1 ticket)
2. Technology & Best Practices Research (1-2 tickets)
3. Architecture & Design Planning (1-2 tickets)  
4. Detailed Requirement Breakdown (1-3 tickets depending on Epic complexity)
5. Implementation Planning & Ticket Generation (1 ticket)

Save each ticket to: docs/Planning/Tickets/[Epic-Name]/Research-Audit/[ticket-number]-[short-description].md

After creating all Research/Audit tickets, provide a summary of:
- Total tickets created
- Key research areas identified
- Critical dependencies discovered
- Recommended next steps for Epic execution

Please proceed systematically and thoroughly. This Epic's success depends on comprehensive research and planning.
```

## Notes
[Any additional context, architectural decisions, or important considerations specific to this Epic]

## Epic Completion Checklist
- [ ] All Research/Audit tickets completed and reviewed
- [ ] All Implementation tickets completed and tested
- [ ] All Test Implementation tickets passed
- [ ] All Code Cleanup tickets completed
- [ ] Documentation updated and reviewed
- [ ] Package integration tested
- [ ] User acceptance criteria validated
- [ ] Epic goals and objectives achieved
