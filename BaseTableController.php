<?php

namespace App\Http\Controllers\TableControllers\BaseTableController;

use App\Helpers\System\MrCacheHelper;
use App\Helpers\System\MtFloatHelper;
use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use JetBrains\PhpStorm\ArrayShape;

class BaseTableController extends Controller
{
  const TABLE_DIR = "App\\Http\\Controllers\\TableControllers";

  protected string $routeName = 'base_table';
  protected array $request;
  protected $header;
  protected $body;
  protected int $count = 0;
  protected $btnSelected;
  protected $result;
  protected bool $isCheckboxes = false;
  protected string $route_url;
  protected $form;
  protected array $filterArgs;
  protected static bool $isFrontEnd = false;
  private array $rows;
  private array $frontRows = [];


  private static bool $debug = false;

  public function __construct(bool $showStart = true)
  {
    $this->request = request()->all();
    $this->show_start = $showStart;
    $arr = explode('\\', static::class);
    $arr = array_pop($arr);

    $param = '?' . $arr . '&';
    foreach($this->request as $key => $value) {
      $param .= $key . '=' . $value;
    }

    $this->route_url = route($this->routeName) . $param;

    // Table filter
    $this->filterArgs = self::GetFilterArgs();
    if(method_exists($this, 'getFilter')) {
      $this->form = $this->getFilter($this->filterArgs);
    }
  }

  public function returnInputData(): array
  {
    return $this->request;
  }

  public function buildTable(array $args = array()): static
  {
    // Checkboxes Selected
    $result = '';
    if(isset(request()['method'])) {
      $methodNameForSelected = request()['method'];

      return $this->$methodNameForSelected($this->request['selected']);
    }

    // Btn Selected
    $this->btnSelected = array();
    if(method_exists($this, 'Selected')) {
      $this->btnSelected = $this->Selected();
    }

    $pageNumber = $this->request['page'] ?? 1;

    $data = $this->GetTableRequest($args)->paginate(self::colInPage($this->filterArgs), ['id'], 'page', $pageNumber);
    // Table header
    $header = $this::getHeader();
    $this->isCheckboxes = false;
    if(count($header)) {
      foreach($header as $head_arr) {
        if(isset($head_arr['name']) && $head_arr['name'] === '#checkbox') {
          $this->isCheckboxes = true;
        }
      }
    }

    $this->header = $header;

    $collections = $data->getCollection();

    $this->rows = array();

    foreach($collections as $model) {
      $this->rows[] = $row = $this->buildRow($model->id, $args);

      if(self::$isFrontEnd) {
        $this->frontRows[] = $row;
      }

      $data->setCollection(collect($this->rows));
    }

    $this->body = $data ?? null;
    $this->count = $data->total();


    $this->result = $result;
    $this->form = $filter ?? null;

    if(self::$debug) {
      dd($this);
    }

    return $this;
  }

  private function convertToApi(array $row): array
  {
    $newRow = array();

    foreach($row as $key => $item) {
      $newRow[$this->header[$key]['name']] = $item;
    }

    return $newRow;
  }

  /**
   * Table arguments
   */
  protected static function GetFilterArgs(): array
  {
    $urlArgs = array();

    foreach(explode('&', request()->getQueryString()) as $item) {
      if($item === 'debug=') {
        self::$debug = true;
      }

      $param = explode('=', $item);
      if(count($param)) {
        if(isset($param[1])) {
          $urlArgs[$param[0]] = urldecode($param[1]);
        }
      }
    }

    return $urlArgs;
  }

  /**
   * Определение типа и направление сортировки.
   * Сортировка только по полям, которые есть в модели, остальные игнорируются
   */
  private function tableSort(&$query)
  {
    $fieldName = 'id';
    $sort = 'asc';

    foreach(explode('&', request()->getQueryString()) as $item) {
      if(!$item) {
        continue;
      }

      $param = explode('=', $item);

      $key = $param[0];
      $value = $param[1];

      if($key === 'sort' && ($value === 'asc' || $value === 'desc')) {
        $sort = $value;
      }
      elseif($key === 'field' && !empty($value)) {
        $fieldName = $value;
      }
    }

    $query->orderBy($fieldName, $sort);
  }

  /**
   * Количество строк на странице
   */
  protected static function colInPage(array $filter): int
  {
    $cnt = 15;
    if(!empty($filter['per_page']) && (int)$filter['per_page']) {
      $cnt = (int)$filter['per_page'];
    }

    return $cnt;
  }

  /**
   * Рендерит таблицу с фильтром
   */
  public function render(): string
  {
    $out = array(
      'form'      => $this->form,
      'route_url' => $this->route_url,
      'mr_object' => array(),
    );

    if($this->body) {
      $out['mr_object'] = array(
        'header'        => $this->header,
        'body'          => $this->body,
        'count'         => $this->count,
        'btn_selected'  => $this->btnSelected,
        'result'        => $this->result,
        'is_checkboxes' => $this->isCheckboxes,
        'route_url'     => $this->route_url,
      );
    }

    return View('layouts.Elements.mr_table')->with($out)->toHtml();
  }

  /**
   * Возвращает массив данных объекта
   */
  #[ArrayShape([
    'header'        => "",
    'body'          => "",
    'count'         => "int",
    'btn_selected'  => "",
    'result'        => "",
    'is_checkboxes' => "bool",
    'route_url'     => "string",
    'form'          => "mixed"
  ])]
  public function getTableData(): array
  {
    return array(
      'header'        => $this->header,
      'body'          => $this->body,
      'count'         => $this->count,
      'btn_selected'  => $this->btnSelected,
      'result'        => $this->result,
      'is_checkboxes' => $this->isCheckboxes,
      'route_url'     => $this->route_url,
      'form'          => $this->form,
    );
  }

  /**
   * Return response for REST API (draft)
   */
  #[ArrayShape([
    'header'       => "",
    'total'        => "mixed",
    'totalDisplay' => "string",
    'data'         => "array",
    'current_page' => "mixed",
    'last_page'    => "mixed",
    'per_page'     => "mixed",
    'form'         => "mixed"
  ])]
  public function getFrontEndData(): array
  {
    $out = array(
      'header'       => $this->header,
      'total'        => $this->body->total(),
      'totalDisplay' => MtFloatHelper::formatCommon($this->body->total(), 0),
      'data'         => $this->frontRows,
      'current_page' => $this->body->currentPage(),
      'last_page'    => $this->body->lastPage(),
      'per_page'     => $this->body->perPage(),
    );

    if($this->form) {
      $out['form'] = $this->form;
    }

    return $out;
  }

  /**
   * Вернёт SQL запрос для построения таблицы
   */
  public function getTableRequest(array $args = array())
  {
    $args += $this->request;

    /** @var Builder $query */
    $query = $this->GetQuery($this->filterArgs, $args);
    $this->tableSort($query);

    return $query;
  }

  // TODO: change to magick method
  private array $tables = array(
    'currency'             => self::TABLE_DIR . "Reference\\MrReferenceCurrencyTableController",
    'country'              => self::TABLE_DIR . "Reference\\MrReferenceCountryTableController",
    'currency_rate'        => self::TABLE_DIR . "Reference\\MrReferenceCurrencyRateTableController",
    'place'                => self::TABLE_DIR . "MrAdminPlaceTableController",
    'price'                => self::TABLE_DIR . "Office\\MrPriceTableController",
    'marketplaceGoodPrice' => self::TABLE_DIR . "Office\\MrMarketplaceGoodTableController",
    'officeGoods'          => self::TABLE_DIR . "Office\\MrOfficeGoodTableController",
  );

  private function DirFilesR($dir)
  {
    $handle = opendir($dir) or die("Can't open directory $dir");
    $files = array();
    while(false !== ($file = readdir($handle))) {
      if($file != "." && $file != "..") {
        if(is_dir($dir . "/" . $file)) {
          $subfiles = $this->DirFilesR($dir . "/" . $file);
          $files = array_merge($files, $subfiles);
        }
        else {
          $files[] = $dir . "/" . $file;
        }
      }
    }
    closedir($handle);

    return $files;
  }

  /**
   * Cached dir tables
   */
  private function getLocalDirs(): array
  {
    return MrCacheHelper::getCachedData('LocalDirs_TableControllers', function() {
      $dir = __DIR__ . '/..';
      $list = $this->DirFilesR($dir);
      array_walk($list, function(&$item) use ($dir) {
        $item = str_replace($dir, '', $item);
      });

      return $list;
    });
  }

  /**
   * Определение нужной таблицы по внешнему запросу
   */
  public function getTableClass(): array
  {
    foreach($this->request as $key => $item) {
      if(strpos($key, 'TableController')) {
        $object = null;
        $localDirs = $this->getLocalDirs();
        foreach($localDirs as $hasDir) {
          if(!stripos($hasDir, $key)) {
            continue;
          }

          $hasDir = str_replace('/', "\\", $hasDir);
          $hasDir = str_replace('.php', "", $hasDir);

          if(class_exists(self::TABLE_DIR . $hasDir, true)) {
            $object = self::TABLE_DIR . $hasDir;
            break;
          }
        }

        if($object) {
          $r = new $object();

          return $r->buildTable()->getTableData();
        }
      }
    }

    /// Draft version for REST API app
    if($this->request['table'] ?? null) {
      self::$isFrontEnd = true;

      if($this->tables[$this->request['table']] ?? null) {
        $object = $this->tables[$this->request['table']];

        $r = new $object();

        return $r->buildTable()->getFrontEndData();
      }
    }


    return ['Table not found'];
  }

  public function getForm()
  {
    return $this->form;
  }
}