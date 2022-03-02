/*
 * Copyright (c) 2017 Tropotek (www.tropotek.com)
 * Created by mifsudm on 18/01/17.
 */

if (typeof (String.prototype.hashCode) === 'undefined') {

  String.prototype.hashCode = function () {
    var a = 1, c = 0, h, o;
    var s = this;
    if (s) {
      a = 0;
      /*jshint plusplus:false bitwise:false*/
      for (h = s.length - 1; h >= 0; h--) {
        o = s.charCodeAt(h);
        a = (a<<6&268435455) + o + (o<<14);
        c = a & 266338304;
        a = c!==0?a^c>>21:a;
      }
    }
    return String(a);
  }

}

/**
 * This plugin template is from http://stefangabos.ro/jquery/jquery-plugin-boilerplate-revisited/
 *
 * jQuery Plugin Boilerplate
 * A boilerplate for jumpstarting jQuery plugins development
 * version 1.1, May 14th, 2011
 * by Stefan Gabos
 *
 * <code>
 *   $(document).ready(function() {
 *     // attach the plugin to an element
 *     $('#element').columnSelect({'foo': 'bar'});
 *
 *     // call a public method
 *     $('#element').data('columnSelect').foo_public_method();
 *
 *     // get the value of a property
 *     $('#element').data('columnSelect').settings.foo;
 *
 *   });
 * </code>
 */

// remember to change every instance of "columnSelect" to the name of your plugin!
(function($) {

  /**
   * @param element
   * @param options
   */
  var columnSelect = function(element, options) {

    // plugin's default options
    // this is private property and is  accessible only from inside the plugin
    var defaults = {
      sid: 'column-select-00',
      buttonId : '',
      ajaxUrl: '',
      reset: false,
      selectors: null,
      disabled : [],
      disabledColor: '#999',
      disabledHidden: true,
      defaultSelected : [],
      defaultUnselected : [],
      defaultHidden : [],   // Hide these from the dropdown list

      // Should return a jquery list containing checkbox inputs to trigger the columns.
      onInitColumnSelectors: function(topRow) {
        // by default the plugin will look for a button $('#buttonId') to append a list to..
        var button = $('#'+plugin.settings.buttonId);
        if (!button.length || !button.hasClass('btn')) {
          console.log('No button found!');
          return;
        }

        //button.detach();
        var btnGroup = $('<div class="btn-group tk-column-select"></div>');
        btnGroup.insertBefore(button);
        button.removeAttr('type');
        button.addClass('dropdown-toggle').attr('data-toggle', 'dropdown');
        button.append(' <span class="caret"></span>');
        button.detach();
        btnGroup.append(button);

        // List templates
        var ul = $('<ul class="dropdown-menu checkbox-menu columnSelect"></ul>');
        var tpl = $('<li><label for="" class="small"><input type="checkbox" id="" value="_fieldName"/> <span>Field Name</span></label></li>');

        topRow.each(function (i) {
          var row = tpl.clone();
          var name  = plugin.settings.sid;
          var rowId = name + '-' + i;
          var label = 'Column ' + i;
          if($(this).attr('data-label')) {
            label = $(this).attr('data-label');
          }

          row.find('input').attr('id', rowId);
          row.find('input').attr('name', name);
          row.find('input').attr('value', i);
          row.find('span').text(decodeHTMLEntities(label));
          row.find('input').prop('checked', true);
          row.find('label').attr('for', rowId);

          if (isArray(plugin.settings.disabled) && $.inArray(i, plugin.settings.disabled) !== -1) {
            row.addClass('disabled');
            row.find('label').css('color', plugin.settings.disabledColor);
            row.find('input').attr('readonly', 'readonly');
            if (plugin.settings.disabledHidden) {
              row.hide();
            }
          }

          if (isArray(plugin.settings.defaultHidden) && $.inArray(i, plugin.settings.defaultHidden) !== -1) {
            row.hide();
          }
          ul.append(row);
        });
        btnGroup.append(ul);

        // Allow Bootstrap dropdown menus to have forms/checkboxes inside,
        // and when clicking on a dropdown item, the menu doesn't disappear.
        ul.on('click', function(e) {
          e.stopPropagation();
        });

        return ul.find('input');
      },

      // called when a user selects an item in the column list
      onChange: function () { },

      onSaveState: function () {
        var data = [];
        plugin.settings.selectors.each(function (i) {
          if ($(this).attr('data-cs-checked') === '1') {
            data[data.length] = {name: $(this).attr('name'), value: $(this).attr('data-cs-coll')};
          }
        });
        var json = JSON.stringify(data);
        setSessionParam(plugin.settings.sid, json);
      },


      onRestoreState: function() {
        if (plugin.settings.reset) {
          removeSessionParam(plugin.settings.sid);
        }

        // TODO: Get the default state of the columns (some may be hidden by default?)
        // How do we setup default hidden cols, maybe via the settings or a data attr in the headers?????
        var selected = plugin.range(0, plugin.settings.selectors.length-1);
        if (isArray(plugin.settings.defaultSelected) && plugin.settings.defaultSelected.length > 0) {
          selected = plugin.settings.defaultSelected;
        }
        if (isArray(plugin.settings.defaultUnselected) && plugin.settings.defaultUnselected.length > 0) {
          for(var i = 0; i < plugin.settings.defaultUnselected.length; i++) {
            var idx = selected.indexOf(plugin.settings.defaultUnselected[i]);
            if (idx !== -1) {
              selected.splice(idx, 1);
            }
          }
        }

        //var state = Cookies.get(plugin.settings.sid);
        getSessionParam(plugin.settings.sid, function (data) {
          if (data != undefined && data.value) {
            try {
              var value = JSON.parse(data.value);
              var sel = [];
              $.each(value, function (i, o) {
                sel[sel.length] = o.value;
              });
              selected = sel;
            } catch(e) { console.warn(e); }
          }
          plugin.settings.selectors.each(function(i) {
            if ($.inArray($(this).attr('data-cs-coll'), selected) !== -1) {
              $(this).prop('checked', true).attr('data-cs-checked', 1);
            } else {
              $(this).prop('checked', false).attr('data-cs-checked', 0);
            }
          });
          refresh();
        });

      }

    };

    // to avoid confusions, use "plugin" to reference the
    // current instance of the object
    var plugin = this;
    var $element = $(element); // reference to the jQuery version of DOM element
    var table = null;
    var form = null;
    var topRow = null;

    // this will hold the merged default, and user-provided options
    // plugin's properties will be available through this object like:
    // plugin.settings.propertyName from inside the plugin or
    // element.data('columnSelect').settings.propertyName from outside the plugin,
    // where "element" is the element the plugin is attached to;
    plugin.settings = {};


    // the "constructor" method that gets called when the object is created
    plugin.init = function() {

      // the plugin's final properties are the merged default and
      // user-provided options (if any)
      plugin.settings = $.extend({}, defaults, $element.data(), options);

      if (plugin.settings.sid === '') {
        plugin.settings.sid = document.location.pathname.hashCode() + table.attr('id')
      }

      if (isArray(plugin.settings.defaultSelected)) {
        $.each(plugin.settings.defaultSelected, function (i, o) {
          plugin.settings.defaultSelected[i] = plugin.settings.defaultSelected[i] + '';
        });
      }
      if (isArray(plugin.settings.defaultUnselected)) {
        $.each(plugin.settings.defaultUnselected, function (i, o) {
          plugin.settings.defaultUnselected[i] = plugin.settings.defaultUnselected[i] + '';
        });
      }
      if (isArray(plugin.settings.disabled)) {
        $.each(plugin.settings.disabled, function (i, o) {
          plugin.settings.disabled[i] = plugin.settings.disabled[i] + '';
        });
      }

      // locate table
      table = $element.closest('.tk-table').find('table');
      if (table.length === 0) {
        console.error('jquery.columnSelect Error: No valid table found!');
        return;
      }

      // Locate form
      form = $element.closest('form');
      if (form.length && $(form).prop('action')) {
        plugin.settings.ajaxUrl = $(form).prop('action');
      }
      // This is required so the crumbs are not messed up...
      plugin.settings.ajaxUrl = addParam(plugin.settings.ajaxUrl, 'crumb_ignore', 'crumb_ignore');
      plugin.settings.ajaxUrl = addParam(plugin.settings.ajaxUrl, 'nolog', 'nolog');


      // Get the first row that we will use to setup the column selector
      topRow = table.find('th');
      if (!topRow || !topRow.length) {
        topRow = table.find('tr:nth-child(1) td');
      }
      if (!topRow.length) {
        console.error('jquery.columnSelect Error: No valid first row found.');
        return;
      }

      if (plugin.settings.onInitColumnSelectors !== undefined) {
        plugin.settings.selectors = plugin.settings.onInitColumnSelectors.call(table, topRow);
      }

      if(!plugin.settings.selectors || !plugin.settings.selectors.length) {
        console.error('jquery.columnSelect Error: No valid column selectors found.');
        return;
      }

      // Setup an internal check value for each selector item
      plugin.settings.selectors.each(function(i) {
        $(this).attr('data-cs-coll', i);
      });

      plugin.settings.selectors.on('click', function (e) {
        if (plugin.settings.disabled !== null && $.inArray($(this).attr('data-cs-coll'), plugin.settings.disabled) !== -1) {
          e.stopPropagation();return false;
        }

        var state = 0;
        if (!$(this).attr('data-cs-checked') || $(this).attr('data-cs-checked') === '' || $(this).attr('data-cs-checked') === '1') {
          state = 0;
        } else {
          state = 1;
        }
        $(this).attr('data-cs-checked', state);

        plugin.settings.onChange.call(this);
        refresh();
        plugin.settings.onSaveState.call(this);

      });
      plugin.settings.onRestoreState.call(this);
    };

    var addParam = function(url, param, value) {
       var a = document.createElement('a'), regex = /(?:\?|&amp;|&)+([^=]+)(?:=([^&]*))*/g;
       var match, str = []; a.href = url; param = encodeURIComponent(param);
       while (match = regex.exec(a.search))
           if (param !== match[1]) str.push(match[1]+(match[2]?"="+match[2]:""));
       str.push(param+(value?"="+ encodeURIComponent(value):""));
       a.search = str.join("&");
       return a.href;
    };

    // private methods
    // these methods can be called only from inside the plugin like:
    // methodName(arg1, arg2, ... argn)

    var setSessionParam = function(name, value, callback) {
      //console.log('Set("'+name+'", "'+value+'")');
      var params = {action: 'session.set', name : name, value: value};
      $.post(plugin.settings.ajaxUrl, params, function (data) {
        if (callback)
          callback.apply(params, [data]);
      });
    };

    var getSessionParam = function(name, callback) {
      //console.log('Get("'+name+'")');
      var params = {action: 'session.get', name : name};
      $.post(plugin.settings.ajaxUrl, params, function (data) {
        if (callback)
          callback.apply(params, [data]);
      });
    };

    var removeSessionParam = function(name, callback) {
      //console.log('Remove("'+name+'")');
      var params = {action: 'session.remove', name : name};
      $.post(plugin.settings.ajaxUrl, params, function (data) {
        if (callback)
          callback.apply(params, [data]);
      });
    };


    var decodeHTMLEntities = function(text) {
      var entities = [
        ['amp', '&'],
        ['apos', '\''],
        ['#x27', '\''],
        ['#x2F', '/'],
        ['#39', '\''],
        ['#47', '/'],
        ['lt', '<'],
        ['gt', '>'],
        ['nbsp', ' '],
        ['quot', '"']
      ];

      for (var i = 0, max = entities.length; i < max; ++i)
        text = text.replace(new RegExp('&'+entities[i][0]+';', 'g'), entities[i][1]);

      return text;
    };

    var refresh = function() {
      // code goes here
      plugin.settings.selectors.each(function(i) {
        var nth = parseInt($(this).attr('data-cs-coll'))+1;
        var cells = table.find('tr th:nth-child('+nth+'), tr td:nth-child('+nth+')');
        if (!$(this).attr('data-cs-checked') || $(this).attr('data-cs-checked') === '' || $(this).attr('data-cs-checked') === '1') {
          cells.show();
        } else {
          cells.hide();
        }
      });
    };

    var isArray = function(obj) {
      return !!obj && Array === obj.constructor;
    };


    // public methods
    // these methods can be called like:
    // plugin.methodName(arg1, arg2, ... argn) from inside the plugin or
    // element.data('columnSelect').publicMethod(arg1, arg2, ... argn) from outside 
    // the plugin, where "element" is the element the plugin is attached to

    plugin.range = function(start, end) {
      var foo = [];
      for (var i = start; i <= end; i++) {
        foo.push(i+'');
      }
      return foo;
    };

    // fire up the plugin!
    plugin.init();
  };
  // add the plugin to the jQuery.fn object
  $.fn.columnSelect = function(options) {
    return this.each(function() {
      if (undefined === $(this).data('columnSelect')) {
        var plugin = new columnSelect(this, options);
        $(this).data('columnSelect', plugin);
      }
    });
  }
})(jQuery);