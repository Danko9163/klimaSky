<?php namespace Backend\FormWidgets;

use Carbon\Carbon;
use Backend\Classes\FormWidgetBase;
use System\Helpers\DateTime as DateTimeHelper;

/**
 * DatePicker renders a date picker field
 *
 * @package october\backend
 * @author Alexey Bobkov, Samuel Georges
 */
class DatePicker extends FormWidgetBase
{
    //
    // Configurable Properties
    //

    /**
     * @var bool mode for display. Values: datetime, date, time
     */
    public $mode = 'datetime';

    /**
     * @var string format provides an explicit date display format
     */
    public $format;

    /**
     * @var string minDate the minimum/earliest date that can be selected.
     * eg: 2000-01-01
     */
    public $minDate;

    /**
     * @var string maxDate the maximum/latest date that can be selected.
     * eg: 2020-12-31
     */
    public $maxDate;

    /**
     * @var string yearRange number of years either side or array of upper/lower range
     * eg: 10 or [1900,1999]
     */
    public $yearRange;

    /**
     * @var string|array disableDays are days that cannot be selected. Value can be a number
     * to represent Sunday (0) to Saturday (6), or an explicit date (2024-10-01).
     */
    public $disableDays;

    /**
     * @var int firstDay of the week
     * eg: 0 (Sunday), 1 (Monday), 2 (Tuesday), etc.
     */
    public $firstDay = 0;

    /**
     * @var bool twelveHour clock
     */
    public $twelveHour = false;

    /**
     * @var bool hoursOnly removes the need to select minutes
     */
    public $hoursOnly = false;

    /**
     * @var bool showWeekNumber at head of row
     */
    public $showWeekNumber = false;

    /**
     * @var bool useTimezone will convert the date and time to the user preference
     */
    public $useTimezone = true;

    /**
     * @var bool defaultTimeMidnight If the time picker is enabled but the time value is not provided fallback to 00:00.
     * If this option is disabled, the default time is the current time.
     */
    public $defaultTimeMidnight = false;

    //
    // Object Properties
    //

    /**
     * @inheritDoc
     */
    protected $defaultAlias = 'datepicker';

    /**
     * @inheritDoc
     */
    public function init()
    {
        $this->fillFromConfig([
            'format',
            'mode',
            'minDate',
            'maxDate',
            'yearRange',
            'disableDays',
            'firstDay',
            'twelveHour',
            'hoursOnly',
            'showWeekNumber',
            'useTimezone',
            'defaultTimeMidnight'
        ]);

        $this->mode = strtolower($this->mode);

        // @deprecated API
        if ($this->getConfig('ignoreTimezone', false)) {
            $this->useTimezone = false;
        }

        if ($this->mode === 'time' || $this->mode === 'date') {
            $this->useTimezone = false;
        }

        if ($this->minDate !== null) {
            $this->minDate = is_int($this->minDate)
                ? Carbon::createFromTimestamp($this->minDate)
                : Carbon::parse($this->minDate);
        }

        if ($this->maxDate !== null) {
            $this->maxDate = is_int($this->maxDate)
                ? Carbon::createFromTimestamp($this->maxDate)
                : Carbon::parse($this->maxDate);
        }
    }

    /**
     * @inheritDoc
     */
    public function render()
    {
        $this->prepareVars();
        return $this->makePartial('datepicker');
    }

    /**
     * prepareVars for display
     */
    public function prepareVars()
    {
        if ($value = $this->getLoadValue()) {
            $value = DateTimeHelper::makeCarbon($value, false);
            $value = $value instanceof Carbon ? $value->toDateTimeString() : $value;
        }

        $this->vars['name'] = $this->getFieldName();
        $this->vars['value'] = $value ?: '';
        $this->vars['field'] = $this->formField;
        $this->vars['mode'] = $this->mode;
        $this->vars['minDate'] = $this->minDate;
        $this->vars['maxDate'] = $this->maxDate;
        $this->vars['yearRange'] = $this->yearRange;
        $this->vars['disableDays'] = $this->getDisableDaysString();
        $this->vars['firstDay'] = $this->firstDay;
        $this->vars['twelveHour'] = $this->twelveHour;
        $this->vars['hoursOnly'] = $this->hoursOnly;
        $this->vars['showWeekNumber'] = $this->showWeekNumber;
        $this->vars['useTimezone'] = $this->useTimezone;
        $this->vars['format'] = $this->format;
        $this->vars['formatMoment'] = $this->getDateFormatMoment();
        $this->vars['formatAlias'] = $this->getDateFormatAlias();
        $this->vars['defaultTimeMidnight'] = $this->defaultTimeMidnight;
    }

    /**
     * @inheritDoc
     */
    public function getSaveValue($value)
    {
        if (!strlen($value)) {
            return null;
        }

        return $value;
    }

    /**
     * resetFormValue from the form field
     */
    public function resetFormValue()
    {
        // Transfer approved config
        $this->minDate = $this->formField->minDate;
        $this->maxDate = $this->formField->maxDate;
        $this->yearRange = $this->formField->yearRange;
        $this->firstDay = $this->formField->firstDay;
        $this->twelveHour = $this->formField->twelveHour;
        $this->hoursOnly = $this->formField->hoursOnly;
        $this->showWeekNumber = $this->formField->showWeekNumber;
        $this->disableDays = $this->formField->disableDays;
    }

    /**
     * getDateFormatMoment converts PHP format to JS format
     */
    protected function getDateFormatMoment()
    {
        if ($this->format) {
            return DateTimeHelper::momentFormat($this->format);
        }
    }

    /**
     * getDisableDaysString allows setting the disableDays property as a string
     * that refers to a callable method
     */
    protected function getDisableDaysString()
    {
        if ($callableMethod = $this->formField->getCallableMethodFromValue($this->disableDays)) {
            return $callableMethod($this->model);
        }

        return $this->disableDays;
    }

    /*
     * getDateFormatAlias displays alias, used by preview mode
     */
    protected function getDateFormatAlias()
    {
        if ($this->format) {
            return null;
        }

        if ($this->mode == 'time') {
            return 'time';
        }
        elseif ($this->mode == 'date') {
            return 'dateLong';
        }
        else {
            return 'dateTimeLong';
        }
    }
}
