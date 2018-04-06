# NotificationAry

Plugin sends emails to define groups of users or users if an article is added or updated at your web-site.

*See for more information:* http://gruz.org.ua/extensions/notificationary-get-email-notification-when-an-article-is-added-or-changed.html


# PhocaDownload

Custom template should be (empty lines are important):

```
com_phocadownload.upload
TablePhocaDownload
index.php?option=com_zoo&task=item&item_id=##ID##
index.php?option=com_zoo&view=submission&layout=submission&submission_id=&type_id=article&item_id=##ID##&redirect=itemedit&submission_hash=##SUBMISSION_HASH##
index.php?option=com_zoo&controller=item&task=edit&cid%5B%5D=##ID##
PhocaDownloadModelCategory





phocadownloadfile
PhocaDownloadRoute::getFileRoute
```

