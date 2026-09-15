Use Conventional Commits format: type(scope): description

Types:
- feat: new feature
- fix: bug fix
- docs: documentation
- style: formatting, no logic change
- refactor: code restructuring
- perf: performance improvement
- test: adding/fixing tests
- chore: build, deps, misc
- ci: CI/CD changes

Rules:
- Imperative mood ("Add feature" not "Added feature")
- Subject under 72 chars
- Body explains WHY, not what
- Blank line between subject and body
- Breaking changes: append `!` after type/scope, add `BREAKING CHANGE:` footer