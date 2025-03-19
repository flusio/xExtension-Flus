# Changelog of xExtension-Flus

## 2025-03-19 - v1.2.1

### Bug fixes

- Don't change users' last activity on notification ([523af57](https://github.com/flusio/xExtension-Flus/commit/523af57))
- Add a new line at the end of a `notify_inactive_accounts` log ([00e109d](https://github.com/flusio/xExtension-Flus/commit/00e109d))

## 2025-03-08 - v1.2.0

### New

- Delete accounts after 1 year of inactivity ([50f876f](https://github.com/flusio/xExtension-Flus/commit/50f876f))

### Improvements

- Improve the home page ([539c902](https://github.com/flusio/xExtension-Flus/commit/539c902))

### Documentation

- Provide a changelog ([dc5553c](https://github.com/flusio/xExtension-Flus/commit/dc5553c))
- Add more information in the README ([64b53a5](https://github.com/flusio/xExtension-Flus/commit/64b53a5), [795dcad](https://github.com/flusio/xExtension-Flus/commit/795dcad))

### Developers

- Provide a pull request template ([a5c5cca](https://github.com/flusio/xExtension-Flus/commit/a5c5cca))
- Provide a make command to release a new version ([27c42d3](https://github.com/flusio/xExtension-Flus/commit/27c42d3))
- Provide a make command to open a shell in the FRSS container ([8551f63](https://github.com/flusio/xExtension-Flus/commit/8551f63))
- Update the copyright headers ([8f31aef](https://github.com/flusio/xExtension-Flus/commit/8f31aef))

## 2024-10-28 - v1.1.0

### New

- Add a script to export the list of users ([e845df7](https://github.com/flusio/xExtension-Flus/commit/e845df7))
- Add a script to clean unvalidated accounts ([6f0a63a](https://github.com/flusio/xExtension-Flus/commit/6f0a63a))

### Bug fixes

- Update outdated information ([6e11005](https://github.com/flusio/xExtension-Flus/commit/6e11005))
- Init view in Mailer correctly ([1081d03](https://github.com/flusio/xExtension-Flus/commit/1081d03))
- Fix errors "Creation of dynamic property is deprecated" ([30aef06](https://github.com/flusio/xExtension-Flus/commit/30aef06))
- Fix verification of emails validation ([56e6919](https://github.com/flusio/xExtension-Flus/commit/56e6919))
- Fix the `sync_subscriptions` script ([68c1037](https://github.com/flusio/xExtension-Flus/commit/68c1037), [d428b57](https://github.com/flusio/xExtension-Flus/commit/d428b57))
- Fix redirection to billing URL ([a585777](https://github.com/flusio/xExtension-Flus/commit/a585777))
- Declare missing class properties ([79b4341](https://github.com/flusio/xExtension-Flus/commit/79b4341))
- Prevent initializing Subscriptions service with empty config ([b938732](https://github.com/flusio/xExtension-Flus/commit/b938732))

### Technical

- Make sure that overdue users are inactive ([15eed32](https://github.com/flusio/xExtension-Flus/commit/15eed32))

### Developers

- Improve coding style and type hinting ([d7af70d](https://github.com/flusio/xExtension-Flus/commit/d7af70d))
- Declare return types in indexController ([7e561ab](https://github.com/flusio/xExtension-Flus/commit/7e561ab))
- Remove unused app icons ([86a1b56](https://github.com/flusio/xExtension-Flus/commit/86a1b56))

## 2023-08-10 - v1.0.1

### Bug fixes

- Fix accessing `system_conf` in the extension file ([4b3eca4](https://github.com/flusio/xExtension-Flus/commit/4b3eca4))

## 2023-08-09 - v1.0.0

First version
