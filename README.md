# Mezzio-Abstract-Request-Handler


You can install this package with the following command:
```composer require marcel-maqsood/mezzio-abstract-request-handler```

## Configuration

Within RequestHandlers that extends our AbstractRequestHandler,
You have to pass atleast a TemplateRenderer;
For advanced functions like generateInsertArray,
you need to submit arrays that contain your table config, otherwise these functions wont do anything.




##### <a id="pdo">persistentpdo - An array, in which we define our database-connection rules:</a>
See MazeDEV/Marcel-Maqsood(https://github.com/marcel-maqsood/DatabaseConnector) for additional informations and documentation.
Our SessionAuthMiddleware uses this DatabaseConnector and therefore requires its configuration set.
Within our default config, we already supply these settings and you just have to adjust them.
Also, PersistentPDO must be included within your applications ```config\autoload\dependencies.global.php``` as it is required for our AbstractRequestHandler.
We already included it within our ```config\dependencies.global.php```.

##### sql log display
When using our AbstractRequestHandler, the handler will pass the attribute "sqlLog" into each template that it renders, making it easy to view which sql statements happened within the rendering.



## Credits

This Software has been developed by MazeDEV/Marcel-Maqsood(https://github.com/marcel-maqsood).


## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
