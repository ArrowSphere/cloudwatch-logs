# ArrowSphere CloudWatch Logs

This composer package allows to log data in CloudWatch, with auto-generated correlation id to follow the logs throughout
all processes and their children.

## ⚙️ Installation

Install the latest version with

```bash
$ composer require arrowsphere/cloudwatch-logs
```

## Request identifiers management

### 📖 Introduction

We manage 3 types of identifiers to help us identify our requests and follow their logs.

The request id, identified as `ars-request-id`, is the unique identifier for the current request. It is auto-generated
and cannot be null.

The parent id, identified as `ars-parent-id`, is the identifier of the parent request, that directly called this one. It
can be null if the current request is the originator of a process.

The correlation id, identified as `ars-correlation-id`, is the identifier of the originator request of the process.

Whenever an event is logged, we should have those 3 headers indicated in the logging context.

### 🔧 How to use

#### The header ids

The three header ids are automatically logged by the logger system provided by this package, however if you want to pass them through to other API calls, you'll have to access them by yourself.

You can access the headers by using class `ArsHeaderManager`.

The simplest way to use this class is to use static method `ArsHeaderManager::initFromGlobals()` with no argument, it works as a singleton, and will populate the ids with whatever is already present in the `$_SERVER` superglobal.

The `$_SERVER` superglobal will then contain all three ids (parent id being an empty string if there's none).

Here's an example of code to make use of the variables:
```php
$arsHeaderManager = \ArrowSphere\CloudWatchLogs\Processor\ArsHeader\ArsHeaderManager::initFromGlobals();

$requestId = $arsHeaderManager->getRequestId();
$correlationId = $arsHeaderManager->getCorrelationId();
$parentId = $arsHeaderManager->getParentId();

// Now you have all three variables and can use them however you want.
```

#### The logger

This package provides a monolog handler for CloudWatch. To use it you'll have to instanciate it with the following parameters:

```php
<?php
$sdkParams = [
    'credentials' => [
        'key' => 'your AWS access key',
        'secret' => 'your AWS secret access key',
    ],
    'region' => 'eu-west-1', // or any other AWS region
    'version' => 'latest', // or whatever seems pertinent here
];
$accountAlias = 'arrowsphere'; // this is the AWS account
$stage = 'prod'; // this is your stage
$application = 'xsp'; // this is your application name
$retentionDays = 30; // Days to keep logs, 90 by default
$batchSize = 1000; // How many log entries to store in memory before sending them to AWS, 10000 by default

$cloudWatchHandler = new \ArrowSphere\CloudWatchLogs\Handler\ArsCloudWatchHandler(
    $sdkParams,
    $accountAlias,
    $application,
    $stage,
    $retentionDays,
    $batchSize
);
```

The handler is now ready to be used, you can add it to your monolog logger with method `pushHandler`.

All logs you'll send to this logger will now be appended to CloudWatch, under the group name `/{account_alias}/{stage}/{application}`.

The logs are formatted by `ArsJsonFormatter`, which provides a unique format for ArrowSphere.
This format is described here: https://confluence.arrowcloud.org/display/XSP/ArrowSphere+logs

This table is a reminder of the fields that can be used in the logs:

| Name            | Mandatory | Type             | Description                                                                                                                                                                                                                                  |
|-----------------|-----------|------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| type            | yes       | string           | The level of the log entry, typically one of the eight [RFC 5424](https://datatracker.ietf.org/doc/html/rfc5424) levels (DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY).                                                   |
| message         | yes       | string           | The message of the log entry, should be a short string describing what the entry is about. Additional context information can and should be included in the context field.                                                                   |
| tags            | yes       | array of strings | Any tags you might want to add in your log. To use the tags, include a `tags` field in the log context.                                                                                                                                      |
| entries         | yes       | array            | This field is present for backward-compatibility reason with xBE but will not be used by this package so it will always be an empty array.                                                                                                   |
 | ars.correlation | yes       | string           | The identifier that will be used between ArrowSphere calls to create a link between the various micro-services.                                                                                                                              |
| ars.request     | yes       | string           | The identifier of the current request.                                                                                                                                                                                                       |
| ars.parent      | yes       | string           | The identifier of the request that directly called the current one, if available (otherwise it's just an empty string).                                                                                                                      |
 | context         | no        | object           | This field is the context used in the log entry, and should contain all variable information you might want to add to make your log entry more useful. For simplicity, the `extra` field from the log entry is also included in the context. |

Here's an example of log:
```json
{
  "type": "INFO",
  "message": "xAC API call to create a subscription",
  "tags": [
    "api_call",
    "xac"
  ],
  "entries": [],
  "ars": {
    "correlation": "00000000-0000-0000-0000-0000AAAA1111",
    "request": "11111111-1111-1111-1111-0000AAAA1111",
    "parent": ""
  },
  "context": {
    "request": {
      "baseUri": "https://xac.example.com/api",
      "endpointUri": "/subscription",
      "method": "POST",
      "body": {
        "customerRef": "XSP12345",
        "arrowspherePriceBandSku": "ABCD-1234"
      },
      "headers": {
        "AC-API-TOKEN": "***** (Obfuscated)",
        "Accept": "application/vnc.xac.v6+json"
      }
    },
    "response": {
      "httpStatusCode": 201,
      "body": {
        "subscriptionLineId": 12345
      },
      "headers": {
        "Content-Type": "application/json"
      }
    },
    "extra": {
      "memoryUsage": "22 MB"
    }
  }
}
```

Please note that the logger uses `ArsHeaderManager` and provides the three header ids in the `ars` object as described above. This is handled by `ArsHeaderProcessor`.

## 📃 License

This package is made available under the MIT License (MIT). Please see [License File](LICENSE) for more information.
