<?php

declare(strict_types=1);

namespace MazeDEV\AbstractRequestHandler;

use MazeDEV\DatabaseConnector\PersistentPDO;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Template\TemplateRendererInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

abstract class AbstractRequestHandler implements RequestHandlerInterface
{
    protected $tableConfig;
    protected $handlerConfig;
    protected $renderer;
    protected $data;
    protected $adminName = null;
    protected $persistentPDO;
    protected $errorMsgs;
    protected $baseTemplate;

    public function __construct(TemplateRendererInterface $renderer, PersistentPDO $persistentPDO = null,
                                array $tableConfig = [], array $handlerConfig = [])
    {
        $this->renderer = $renderer;
        $this->tableConfig = $tableConfig;
        $this->handlerConfig = $handlerConfig;
        $this->persistentPDO = $persistentPDO;
    }

    protected abstract function defaultResponse(ServerRequestInterface $request, array $postData = []): HtmlResponse;

    /**
     * save returns the userhash if the insert was successful, otherwise it returns false.
     * @return string|bool
     */
    protected abstract function save(array $postData): bool | array;
    protected abstract function update(array $entry = []): bool;
    protected abstract function delete(array $postData): JsonResponse;

    /**
     * generateTemplateData returns an array filled with all data that the template needs from our handler,
     * it may be empty if no additional data is required.
     * @return array
     */
    abstract protected function generateTemplateData(array $postData = [], array $feedback = []): array;
    abstract protected function getLookupResult(ServerRequestInterface $request, array $postData = [], $feedBack = []):
    HtmlResponse;
    abstract protected function handleExtraConfigs(ServerRequestInterface $request, array $postData):
    JsonResponse|HtmlResponse|bool;

    /**
     * This function generates Insert Arrays to further use for PDO by looping through $this->tableConfig and gets
     * data within $postData defined behind each key.
     *
     * @param $tableKey - The TableConfig key that contains all fields that we wanna include in the insert Array.
     * @param $postData - The requests POST data.
     *
     * @return array - Empty array or filled with all post fields mapped to the correct table field.
     */
    protected function generateInsertArray(string $tableKey, array $postData): array
    {
        $insert = [];

        if(!isset($this->tableConfig[$tableKey]))
        {
            return [];
        }

        foreach($this->tableConfig[$tableKey] as $key => $value)
        {
            if(!isset($postData[$value]))
            {
                continue;
            }
            if($postData[$value] == '')
            {
                //empty fields are getting ignored as the db will use default values or null for them.
                continue;
            }
            if($value !== $this->tableConfig[$tableKey]['identifier'])
            {
                $insert[$value] = $postData[$value];
            }
        }

        return $insert;
    }

    /**
     * With this function, we grant our Handlers the ability to render HTML without need for extra codes,
     * usefull for XML responses.
     */
    protected function renderHtml(string $templateName, array $attributes = []): string
    {
        if($this->adminName !== null)
        {
            $attributes['adminName'] = $this->adminName;
        }

        return $this->renderer->render(
            $templateName,
            $attributes
        );
    }

    /**
     * @param string $templateName - The name of the desired Template for this response
     *
     * @param int $status - The statuscode of this response, eg. 200, 400.
     *
     * @param array $attributes - The array with all necessary keys for the desired template to render.
     * $attributes can be left blank, if status = 400; but should be defined if status = 200,
     * for correct template rendering.
     *
     * @param array $errors - The array with all error messages happend before this response,
     * giving the user all the feedback he needs.
     * $errors may not be needed if the response has $status = 200, as it might gone trough without a hustle.
     *
     * @return JsonResponse - Might contain HTML to replace within the website or messages,
     * if there were errors that the user might need to know.
     */
    protected function generateJsonResponse(string|null $templateName, int $status, array|null $attributes = [], array|null $errors = [])
    {
        $responseData = [];

        if ($status == 200)
        {
            $responseData['html'] = $this->renderHtml($templateName, $attributes);
        }
        else
        {
            $responseData['messages'] = $errors;
        }
        return new JsonResponse($responseData, $status);
    }

    //This will always return a rendered HtmlResponse that always include our admin-name to show.
    protected function generateResponse(ServerRequestInterface $request, string $templateName, array $postData = []):
    HtmlResponse
    {
        $attributes = $this->generateTemplateData($postData);
        if(!empty($this->handlerConfig))
        {
            if(!isset($this->handlerConfig['searchqueue']))
            {
                return new HtmlResponse($this->renderHtml($templateName, $attributes));
            }
            $queue = $postData[$this->handlerConfig['searchqueue']] ?? '';
            if($queue !== '')
            {
                $attributes[$this->handlerConfig['searchqueue']] = $postData[$this->handlerConfig['searchqueue']];
            }
        }

        return new HtmlResponse($this->renderHtml($templateName, $attributes));
    }

    /**
     * A very basic function to provide us with an easy way to generate basic HTML responses that can contain additional data.
     */
    protected function generateResponseWithAttr(string $templateName, array $attributes = [])
    {
        $attributes['adminName'] = $this->adminName;
        return new HtmlResponse($this->renderHtml($templateName, $attributes));
    }

    /**
     * fetchPostData tries to obtain the data off of the parsedBody or, if empty, from our FormHandler-Middleware.
     *
     * As all handlers that receive POSTs should be able to resolve our Form-handlers prefetched data, we use a inherited function to do so.
     *
     * @param ServerRequestInterface $request - Our current Request
     * @return array - An array containing the fetched data.
     */
    protected function fetchPostData(ServerRequestInterface $request)
    {
        // If our FormHandler middleware has set some data, we can work with it.
        // For POST requests made via AJAX transfers, the data may be within '$request->getBody()->getContents()'
        // instead of '$request->getParsedBody()', and it is retrieved here using '$request->getAttribute('formData', [])'.
        $postData = $request->getAttribute('formData', []);

        if(empty($postData))
        {
            $postData = $request->getParsedBody() ?? [];
        }
        return $postData;
    }

    protected function generateLookupConditions(array $postData)
    {
        $conditions = [];
        $hasQueue = isset($postData[$this->handlerConfig['searchqueue']]);

        if(!$hasQueue || $postData[$this->handlerConfig['searchqueue']] == '')
        {
            //simply return an empty array as conditions if there is no queue defined or its left empty.
            return [];
        }

        foreach ($this->handlerConfig['lookup']['conditions'] as $condition => $data)
        {
            $data['queue'] = $postData[$this->handlerConfig['searchqueue']];
            $conditions[$condition] = $data;
        }

        return $conditions;
    }

    protected function handlePost(ServerRequestInterface $request, string $template) : HtmlResponse|JsonResponse
    {
        $postData = $this->fetchPostData($request);

        if(!isset($postData['config']))
        {
            return $this->defaultResponse($request, $postData);
        }

        /*if ($request->getHeaderLine('X-Requested-With') !== 'XMLHttpRequest')
        {
            //If the POST is not done by AJAX, we serve a default response.
            return $this->defaultResponse($request, $postData);
        }*/

        switch ($postData['config'])
        {
            case 'submit':
                $saved = $this->save($postData);

                if (!$saved)
                {
                    return $this->generateJsonResponse(null, 400, null, $this->errorMsgs);
                }
                return new JsonResponse($saved, 200);
            case 'delete':
                return $this->delete($postData);
            default:
                //As the AbstractRequestHandler was not able to resolve this config file, we pass the data towards our
                // main handler and let it check if it can serve it.
                //As $this->handleExtraConfigs might return a JsonResponse or HtmlResponse if it succeded or a bool
                // if not successful, we check it.
                $result = $this->handleExtraConfigs($request, $postData);

                if ($result == false)
                {
                    return $this->generateJsonResponse(null, 400, null, $this->errorMsgs);
                }
                return $result;
        }
    }

    /**
     * This function can be used by any handler, but the handler must have its own implementation
     * of $this->getLookupResult() as it is only an abstract function inside here.
     *
     * @param ServerRequestInterface $request - The request that is going on
     * @param array $postData - The array that contains the postdata transmitted in $request.
     * @param array $feedBack - The array that may or may not contain feedback for UI.
     *
     * @return HtmlResponse - via $this->getLookupResult(...); which, as stated above,
     * is implemented within the handler itself.
     */
    protected function handleLookup(ServerRequestInterface $request, array &$postData = [], $feedBack = [])
    : HtmlResponse
    {
        if (! isset($postData[$this->handlerConfig['searchqueue']]))
        {
            $postData[$this->handlerConfig['searchqueue']] = "";
        }

        return $this->getLookupResult($request, $postData, $feedBack);
    }
}
