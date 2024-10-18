# Upgrade guidelines

The upgrade guidelines contain all the changes that need careful attention. A manual modification in your code or an update of dependencies might be necessary.

## 2.0.0

In version 1 of this project, we had a dependency on “monolog” version 2., which needs to be upgraded to version 3. for compatibility with Laravel 10.

If you are still using Laravel 9, you can continue with version 1 of cloudwatch-logs. However, if you are using Laravel 10, update the dependency to version 2.

Some methods within the project have been modified. These changes should be transparent unless you have extended those methods.

-----
>ArsJsonFormatter->format(array \$record) to ArsJsonFormatter->format(LogRecord \$record)

>ArsCloudWatchHandler->write(array \$record) to ArsCloudWatchHandler->write(LogRecord \$record)

>ArsCloudWatchHandler->formatRecords(array \$entry) to ArsCloudWatchHandler->formatRecords(LogRecord \$entry)

>ArsHeaderProcessor->__invoke(array \$record): array to ArsHeaderProcessor->__invoke(LogRecord $record): LogRecord

-----

For more information, cf :https://github.com/Seldaek/monolog/blob/main/UPGRADE.md