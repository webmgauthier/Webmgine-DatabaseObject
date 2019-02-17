# Webmgine - DatabaseObject

Simple custom PHP mysql/mariaDb object

## Getting Started

Use composer autload (or include **src/DatabaseObject.php** in your project).
```
require __DIR__ . '/vendor/autoload.php';
```

Create database object instance
```
$databaseInfo = [
    'host' => 'database host',
    'name' => 'database name',
    'user' => 'database user',
    'pass' => 'database pass',
    'port' => 3306, // Optional, default = 3306
    'encoding' => 'UTF8' // Optional, default = 'UTF8',
    'prefix' => '' // Optional, default = '', used to prefix tables in a shared database environment
];
$dbo = new Webmgine\DatabaseObject($databaseInfo);
```

If you set a prefix, use **#__** in front of your table name when writing query.
```
$dbo->from('#__table_name'); // #__ will be replaced by your prefix, with default prefix set, #__table_name will become table_name
```

You can change the **#__** for anything else using **setPrefixTarget** method
```
$dbo->setPrefixTarget('!!!_');
```

Query are saved inside the object, remember to empty the saved values before making a new query
```
$dbo->newQuery();
```

## Select query

Exemple:
```
$dbo->newQuery();
$dbo->select('*');
$dbo->from('#__exemple_table');
$dbo->where('demo=:demo');
$dbo->execute([
    'demo' => $demo
]);
$singleResult = $dbo->getResult(); // Return one result, if many, return the first
$resultsArray = $dbo->getResults(); // Return all results in a array
```
