<?php

namespace App\Http\Controllers\TableControllers\BaseTableController;

use App\Helpers\System\MrCacheHelper;
use App\Helpers\System\MtFloatHelper;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;

class BaseTableController extends Controller
{
  const TABLE_DIR = "App\\Http\\Controllers\\TableControllers\\";

  protected string $route_name = 'base_table';
  protected array $request;
  protected $header;
  protected $body;
  protected int $count = 0;
  protected $btn_selected;
  protected $result;
  protected bool $is_checkboxes = false;
  protected string $route_url;
  protected $form;
  protected array $filter_args;
  protected static bool $isFrontEnd = false;
  private array $rows;
  private array $front_rows = [];


  private static bool $debug = false;

  public function __construct(bool $show_start = true)
  {
    $this->request = request()->all();
    $this->show_start = $show_start;
    $arr = explode('\\', static::class);
    $arr = array_pop($arr);

    $param = '?' . $arr . '&';
    foreach($this->request as $key => $value)
    {
      $param .= $key . '=' . $value;
    }

    $this->route_url = route($this->route_name) . $param;

    // Table filter
    $this->filter_args = self::GetFilterArgs();
    if(method_exists($this, 'getFilter'))
    {
      $this->form = $this->getFilter($this->filter_args);
    }
  }

  public function returnInputData(): array
  {
    return $this->request;
  }


  /**
   * @param array $args Передаваемые аргументы для запроса в БД
   *
   * @return self
   */
  public function buildTable(array $args = array())
  {
    // Checkboxes Selected
    $result = '';
    if(isset(request()['method']))
    {
      $method_name_for_selected = request()['method'];

      return $this->$method_name_for_selected($this->request['selected']);
    }

    // Btn Selected
    $btn_selected = array();
    if(method_exists($this, 'Selected'))
    {
      $btn_selected = $this->Selected();
    }

    $page_number = $this->request['page'] ?? 1;

    $data = $this->GetTableRequest($args)->paginate(self::colInPage($this->filter_args), ['id'], 'page', $page_number);
    // Table header
    $header = $this::getHeader();
    $is_checkboxes = false;
    if(count($header))
    {
      foreach($header as $head_arr)
      {
        if(isset($head_arr['name']) && $head_arr['name'] == '#checkbox')
        {
          $is_checkboxes = true;
        }
      }
    }

    $this->header = $header;

    $collections = $data->getCollection();

    $this->rows = array();

    foreach($collections as $key => $model)
    {
      $id = $model->id;

      $this->rows[] = $row = $this->buildRow($id, $args);

      if(self::$isFrontEnd)
      {
        $this->front_rows[] = $row;
      }

      $data->setCollection(collect($this->rows));
    }

    $this->body = $data ?? null;
    $this->count = $data->total();


    $this->btn_selected = $btn_selected;
    $this->result = $result;
    $this->is_checkboxes = $is_checkboxes;
    $this->form = $filter ?? null;

    if(self::$debug)
    {
      dd($this);
    }

    return $this;
  }

  private function convertToApi(array $row): array
  {
    $newRow = array();

    foreach($row as $key => $item)
    {
      dd($row);
      $newRow[$this->header[$key]['name']] = $item;
    }

    return $newRow;
  }

  /**
   * Table arguments
   *
   * @return array
   */
  protected static function GetFilterArgs(): array
  {
    $url_args = array();

    foreach(explode('&', request()->getQueryString()) as $item)
    {
      if($item == 'debug=')
      {
        self::$debug = true;
      }

      $param = explode('=', $item);
      if(count($param))
      {
        if(isset($param[1]))
        {
          $url_args[$param[0]] = urldecode($param[1]);
        }
      }
    }

    return $url_args;
  }

  /**
   * Определение типа и направление сортировки.
   * Сортировка только по полям, которые есть в модели, остальные игнорируются
   *
   * @param $query
   */
  private function tableSort(&$query)
  {
    $field_name = 'id';
    // Base parametrise
    $sort = 'asc';

    foreach(explode('&', request()->getQueryString()) as $item)
    {
      if(!$item)
      {
        continue;
      }

      $param = explode('=', $item);
      $key = $param[0];
      $value = $param[1];

      if($key == 'sort' && ($value === 'asc' || $value === 'desc'))
      {
        $sort = $value;
      }
      elseif($key === 'field' && $value)
      {
        $field_name = $value;
      }
    }

    $query->orderBy($field_name, $sort);
  }

  /**
   * Количество строк на странице
   *
   * @param array $filter
   * @return int
   */
  protected static function colInPage(array $filter): int
  {
    $cnt = 15;
    if(isset($filter['per_page']) && (int)$filter['per_page'])
    {
      $cnt = (int)$filter['per_page'];
    }

    return $cnt;
  }

  /**
   * Рендерит таблицу с фильтром
   *
   * @return array|string
   */
  public function render()
  {
    $out = array(
      'form'      => $this->form,
      'route_url' => $this->route_url,
      'mr_object' => array(),
    );

    if($this->body)
    {
      $out['mr_object'] = array(
        'header'        => $this->header,
        'body'          => $this->body,
        'count'         => $this->count,
        'btn_selected'  => $this->btn_selected,
        'result'        => $this->result,
        'is_checkboxes' => $this->is_checkboxes,
        'route_url'     => $this->route_url,
      );
    }

    return View('layouts.Elements.mr_table')->with($out)->toHtml();
  }

  /**
   * Возвращает массив данных объекта
   *
   * @return array
   */
  public function getTableData(): array
  {
    return array(
      'header'        => $this->header,
      'body'          => $this->body,
      'count'         => $this->count,
      'btn_selected'  => $this->btn_selected,
      'result'        => $this->result,
      'is_checkboxes' => $this->is_checkboxes,
      'route_url'     => $this->route_url,
      'form'          => $this->form,
    );
  }

  /**
   * Return response for REST API (draft)
   *
   * @return array
   */
  public function getFrontEndData(): array
  {
    $out = array(
      'header'       => $this->header,
      'total'        => $this->body->total(),
      'totalDisplay' => MtFloatHelper::formatCommon($this->body->total(), 0),
      'data'         => $this->front_rows,
      'current_page' => $this->body->currentPage(),
      'last_page'    => $this->body->lastPage(),
      'per_page'     => $this->body->perPage(),
    );

    if($this->form)
    {
      $out['form'] = $this->form;
    }

    return $out;
  }

  /**
   * Вернёт SQL запрос для построения таблицы
   *
   * @param array $args
   * @return Builder
   */
  public function getTableRequest(array $args = array())
  {
    $args += $this->request;

    /** @var Builder $query */
    $query = $this->GetQuery($this->filter_args, $args);
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

  /**
   * Cached dir tables
   *
   * @return array
   */
  private function getLocalDirs(): array
  {
    return MrCacheHelper::GetCachedData('LocalDirs_TableControllers', function() {
      $dir_list = scandir(__DIR__ . '/../');
      $unset = array('.', '..', 'BaseTableController');

      foreach($unset as $e)
      {
        unset($dir_list[array_search($e, $dir_list)]);
      }

      return $dir_list;
    });
  }

  /**
   * Определение нужной таблицы по внешнему запросу
   *
   * @return array
   */
  public function getTableClass(): array
  {
    foreach($this->request as $key => $item)
    {
      if(strpos($key, 'TableController'))
      {
        $object = null;
        $localDirs = $this->getLocalDirs();
        foreach($localDirs as $has_dir)
        {
          if(class_exists(self::TABLE_DIR . $has_dir . '\\' . $key, true))
          {
            $object = self::TABLE_DIR . $has_dir . '\\' . $key;
            break;
          }
        }

        if($object)
        {
          $r = new $object();

          return $r->buildTable()->getTableData();
        }
      }
    }

    /// Draft version for REST API app
    if($this->request['table'] ?? null)
    {
      self::$isFrontEnd = true;

      if($this->tables[$this->request['table']] ?? null)
      {
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