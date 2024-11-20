# Changelog

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
- Fixed phpunit deprecations
- Fixed CHANGELOG
- Fixed phpstan errors
- Upgraded Github actions
- Fixed auto-publish action
- Fixed and upgraded Github actions

## [2.0.0] - 2024-11-18
- Update monolog dependency to 3.0

## [1.2.0] - 2023-03-06
- Remove describe log stream calls and token

## [1.1.1] - 2023-01-13
- Add possibility to create a new stream

## [1.1.0] - 2022-08-19
- Add correlation id into log stream name

## [1.0.1] - 2022-08-11
- Correlation and request ids should be the same if auto generated

## [1.0.0] - 2022-06-08

### Added

- Added `ArsCloudWatchHandler` to manage the logs and upload them to CloudWatch.
- Added `ArsJsonFormatter` to provide the correct format for the logs in AWS CloudWatch.
- Added `ArsHeaderProcessor` to provide the 3 header ids to each log entry

[Unreleased]: https://github.com/ArrowSphere/cloudwatch-logs/compare/2.0.0...HEAD
[2.0.0]: https://github.com/ArrowSphere/cloudwatch-logs/compare/1.2.0...2.0.0
[1.2.0]: https://github.com/ArrowSphere/cloudwatch-logs/compare/1.1.1...1.2.0
[1.1.1]: https://github.com/ArrowSphere/cloudwatch-logs/compare/1.1.0...1.1.1
[1.1.0]: https://github.com/ArrowSphere/cloudwatch-logs/compare/1.0.1...1.1.0
[1.0.1]: https://github.com/ArrowSphere/cloudwatch-logs/compare/1.0.0...1.0.1
[1.0.0]: https://github.com/ArrowSphere/cloudwatch-logs/compare/7951a70a273b5b394fd7fdd34051a6b8e62ebe74...1.0.0
