'use strict';

(function($) {
  $(function() {
    woopt_show_action();
    woopt_show_conditional();
    woopt_show_apply();
    woopt_time_picker();
    woopt_build_value();
    woopt_build_label();
    woopt_terms_init();
    woopt_user_roles_init();
    woopt_apply_terms_init();
    woopt_products_init();
    woopt_sortable();
  });

  $(document).on('change', '.woopt_action_selector', function() {
    var $this = $(this);
    var $action = $this.closest('.woopt_action_td');
    woopt_show_action($action);
    woopt_build_label($this);
    woopt_build_value();
  });

  $(document).on('change', '.woopt_user_roles_select', function() {
    woopt_build_value();
  });

  $(document).on('change', '.woopt_weekday', function() {
    woopt_build_value();
  });

  $(document).on('change', '.woopt_monthday', function() {
    woopt_build_value();
  });

  $(document).on('keyup change', '.woopt_number', function() {
    woopt_build_value();
  });

  $(document).on('change', '.woopt_weekno', function() {
    woopt_build_value();
  });

  $(document).on('change', '.woopt_monthno', function() {
    woopt_build_value();
  });

  $(document).on('change', '.woopt_conditional', function() {
    var $current_conditional = $(this).closest('.woopt_conditional_item');
    woopt_show_conditional($current_conditional);
    woopt_build_value();
  });

  $(document).on('change', '.woopt_price', function() {
    woopt_build_value();
  });

  $(document).on('change', '.woopt_apply_selector', function() {
    var $action = $(this).closest('.woopt_action');
    woopt_show_apply($action);
    woopt_build_value();
    woopt_build_label();
    woopt_terms_init();
    woopt_products_init();
  });

  $(document).on('change', '.woopt_apply_val', function() {
    woopt_build_value();
    woopt_build_label();
  });

  $(document).on('change', '.woopt_apply_conditional_select', function() {
    woopt_apply_terms_init();
  });

  $(document).
      on('change',
          '.woopt_apply_conditional_select, .woopt_apply_conditional_val',
          function() {
            woopt_build_apply_combination();
          });

  $(document).on('click touch', '.woopt_apply_conditional_remove', function() {
    $(this).closest('.woopt_apply_conditional').remove();
    woopt_build_apply_combination();
  });

  $(document).on('click touch', '.woopt_action_heading', function(e) {
    if (($(e.target).closest('.woopt_action_duplicate').length === 0) &&
        ($(e.target).closest('.woopt_action_remove').length === 0)) {
      $(this).closest('.woopt_action').toggleClass('active');
    }
  });

  // search product
  $(document).on('change', '.woopt-product-search', function() {
    var $this = $(this);
    var val = $this.val();
    var _val = '';

    if (val !== null) {
      if (Array.isArray(val)) {
        _val = val.join();
      } else {
        _val = String(val);
      }
    }

    $this.attr('data-val', _val);
    $this.closest('.woopt_action').
        find('.woopt_apply_val').
        val(_val).
        trigger('change');
  });

  // search category
  $(document).on('change', '.woopt-category-search', function() {
    var $this = $(this);
    var val = $this.val();
    var _val = '';

    if (val !== null) {
      if (Array.isArray(val)) {
        _val = val.join();
      } else {
        _val = String(val);
      }
    }

    $this.attr('data-val', _val);
    $this.closest('.woopt_action').
        find('.woopt_apply_val').
        val(_val).
        trigger('change');
  });

  // search terms
  $(document).on('change', '.woopt_terms', function() {
    var $this = $(this);
    var val = $this.val();
    var _val = '';
    var apply = $this.closest('.woopt_action').
        find('.woopt_apply_selector').
        val();

    if (val !== null) {
      if (Array.isArray(val)) {
        _val = val.join();
      } else {
        _val = String(val);
      }
    }

    $this.data(apply, _val);
    $this.closest('.woopt_action').
        find('.woopt_apply_val').
        val(_val).
        trigger('change');
  });

  $(document).on('click touch', '.woopt_new_conditional', function(e) {
    var $current_conditionals = $(this).
        closest('.woopt_action').
        find('.woopt_conditionals');
    var data = {
      action: 'woopt_add_conditional', nonce: woopt_vars.woopt_nonce,
    };

    $.post(ajaxurl, data, function(response) {
      $current_conditionals.append(response);
      woopt_time_picker();
      woopt_show_conditional();
    });

    e.preventDefault();
  });

  $(document).on('click touch', '.woopt_new_apply_conditional', function(e) {
    var $apply_conditionals = $(this).
        closest('.woopt_action').
        find('.woopt_apply_conditionals');
    var data = {
      action: 'woopt_add_apply_conditional', nonce: woopt_vars.woopt_nonce,
    };

    $.post(ajaxurl, data, function(response) {
      $apply_conditionals.append(response);
      woopt_apply_terms_init();
    });

    e.preventDefault();
  });

  $(document).on('click touch', '.woopt_save_actions', function(e) {
    e.preventDefault();

    var $this = $(this);

    $this.addClass('woopt_disabled');
    $('.woopt_actions').addClass('woopt_actions_loading');

    var form_data = $('.woopt_action_val').serialize() || 0;
    var data = {
      action: 'woopt_save_actions',
      pid: $('#post_ID').val(),
      form_data: form_data,
      nonce: woopt_vars.woopt_nonce,
    };

    $.post(ajaxurl, data, function(response) {
      $('.woopt_actions').removeClass('woopt_actions_loading');
      $this.removeClass('woopt_disabled');
    });
  });

  $(document).on('click touch', '.woopt_action_remove', function(e) {
    e.preventDefault();

    if (confirm('Are you sure?')) {
      $(this).closest('.woopt_action').remove();
      woopt_build_value();
    }
  });

  $(document).on('click touch', '.woopt_expand_all', function(e) {
    e.preventDefault();

    $('.woopt_action').addClass('active');
  });

  $(document).on('click touch', '.woopt_collapse_all', function(e) {
    e.preventDefault();

    $('.woopt_action').removeClass('active');
  });

  $(document).on('click touch', '.woopt_conditional_remove', function(e) {
    e.preventDefault();

    if (confirm('Are you sure?')) {
      $(this).closest('.woopt_conditional_item').remove();
      woopt_build_value();
    }
  });

  $(document).on('click touch', '.woopt-import-export', function(e) {
    if (!$('#woopt_import_export').length) {
      $('body').append('<div id=\'woopt_import_export\'></div>');
    }

    $('#woopt_import_export').html('Loading...');

    $('#woopt_import_export').dialog({
      minWidth: 460,
      title: 'Import / Export',
      modal: true,
      dialogClass: 'wpc-dialog',
      open: function() {
        $('.ui-widget-overlay').bind('click', function() {
          $('#woopt_import_export').dialog('close');
        });
      },
    });

    var data = {
      action: 'woopt_import_export', nonce: woopt_vars.woopt_nonce,
    };

    $.post(ajaxurl, data, function(response) {
      $('#woopt_import_export').html(response);
    });

    e.preventDefault();
  });

  $(document).on('click touch', '.woopt-import-export-save', function(e) {
    if (confirm('Are you sure?')) {
      $(this).addClass('disabled');

      var actions = $('.woopt_import_export_data').val();
      var data = {
        action: 'woopt_import_export_save',
        nonce: woopt_vars.woopt_nonce,
        actions: actions,
      };

      $.post(ajaxurl, data, function(response) {
        location.reload();
      });
    }
  });

  $(document).on('click touch', '.woopt_edit', function(e) {
    var pid = $(this).attr('data-pid');
    var name = $(this).attr('data-name');

    if (!$('#woopt_edit_popup').length) {
      $('body').append('<div id=\'woopt_edit_popup\'></div>');
    }

    $('#woopt_edit_popup').html('Loading...');

    $('#woopt_edit_popup').dialog({
      minWidth: 460,
      title: '#' + pid + ' - ' + name,
      modal: true,
      dialogClass: 'wpc-dialog',
      open: function() {
        $('.ui-widget-overlay').bind('click', function() {
          $('#woopt_edit_popup').dialog('close');
        });
      },
    });

    var data = {
      action: 'woopt_edit', nonce: woopt_vars.woopt_nonce, pid: pid,
    };

    $.post(ajaxurl, data, function(response) {
      $('#woopt_edit_popup').html(response);
    });

    e.preventDefault();
  });

  $(document).on('click touch', '.woopt_edit_save', function(e) {
    $(this).addClass('disabled');
    $('.woopt_edit_message').html('...');

    var pid = $(this).attr('data-pid');
    var actions = $('.woopt_edit_data').val();

    var data = {
      action: 'woopt_edit_save',
      nonce: woopt_vars.woopt_nonce,
      pid: pid,
      actions: actions,
    };

    $.post(ajaxurl, data, function(response) {
      $('.woopt_edit_save').removeClass('disabled');
      $('.woopt_edit_message').html(response);
    });
  });

  function woopt_time_picker() {
    $('.woopt_date_time:not(.woopt_picker_init)').wpcdpk({
      timepicker: true, onSelect: function(fd, d) {
        if (!d) {
          return;
        }

        woopt_build_value();
      },
    }).addClass('woopt_picker_init');

    $('.woopt_date:not(.woopt_picker_init)').wpcdpk({
      onSelect: function(fd, d) {
        if (!d) {
          return;
        }

        woopt_build_value();
      },
    }).addClass('woopt_picker_init');

    $('.woopt_date_range:not(.woopt_picker_init)').wpcdpk({
      range: true, multipleDatesSeparator: ' - ', onSelect: function(fd, d) {
        if (!d) {
          return;
        }

        woopt_build_value();
      },
    }).addClass('woopt_picker_init');

    $('.woopt_date_multi:not(.woopt_picker_init)').wpcdpk({
      multipleDates: 5,
      multipleDatesSeparator: ', ',
      onSelect: function(fd, d) {
        if (!d) {
          return;
        }

        woopt_build_value();
      },
    }).addClass('woopt_picker_init');

    $('.woopt_time:not(.woopt_picker_init)').wpcdpk({
      timepicker: true,
      onlyTimepicker: true,
      classes: 'only-time',
      onSelect: function(fd, d) {
        if (!d) {
          return;
        }

        woopt_build_value();
      },
    }).addClass('woopt_picker_init');
  }

  function woopt_terms_init() {
    $('.woopt_terms').each(function() {
      var $this = $(this);
      var apply = $this.closest('.woopt_action').
          find('.woopt_apply_selector').
          val();
      var taxonomy = apply.slice(6);

      if (taxonomy === 'tag') {
        taxonomy = 'product_tag';
      }

      $this.selectWoo({
        ajax: {
          url: ajaxurl, dataType: 'json', delay: 250, data: function(params) {
            return {
              q: params.term, action: 'woopt_search_term', taxonomy: taxonomy,
            };
          }, processResults: function(data) {
            var options = [];
            if (data) {
              $.each(data, function(index, text) {
                options.push({id: text[0], text: text[1]});
              });
            }
            return {
              results: options,
            };
          }, cache: true,
        }, minimumInputLength: 1,
      });

      if (apply !== 'apply_all' && apply !== 'apply_variation' && apply !==
          'apply_not_variation' && apply !== 'apply_product' && apply !==
          'apply_category' && apply !== 'apply_tag' && apply !==
          'apply_combination') {
        // for terms only
        if ((typeof $this.data(apply) === 'string' ||
            $this.data(apply) instanceof
            String) && $this.data(apply) !== '') {
          $this.val($this.data(apply).split(',')).change();
        } else {
          $this.val([]).change();
        }
      }
    });
  }

  function woopt_user_roles_init() {
    $('.woopt_user_roles_select').selectWoo();
  }

  function woopt_apply_terms_init() {
    $('.woopt_apply_terms').each(function() {
      var $this = $(this);
      var taxonomy = $this.closest('.woopt_apply_conditional').
          find('.woopt_apply_conditional_select').
          val();

      if (taxonomy === 'variation' || taxonomy === 'not_variation') {
        $this.hide();
      } else {
        $this.show().selectWoo({
          ajax: {
            url: ajaxurl, dataType: 'json', delay: 250, data: function(params) {
              return {
                q: params.term, action: 'woopt_search_term', taxonomy: taxonomy,
              };
            }, processResults: function(data) {
              var options = [];
              if (data) {
                $.each(data, function(index, text) {
                  options.push({id: text[0], text: text[1]});
                });
              }
              return {
                results: options,
              };
            }, cache: true,
          }, minimumInputLength: 1,
        });
      }
    });

    woopt_build_apply_combination();
  }

  function woopt_products_init() {
    $('.woopt_apply_selector').each(function() {
      var $this = $(this);
      var $val = $this.closest('.woopt_action').find('.woopt_apply_val');
      var products = $this.closest('.woopt_action').
          find('.woopt-product-search').
          attr('data-val');

      if ($this.val() === 'apply_product') {
        $val.val(products).trigger('change');
      }
    });
  }

  function woopt_show_action($action) {
    if (typeof $action !== 'undefined') {
      var show_field_action = $action.find('.woopt_action_selector').
          find(':selected').
          data('show');

      $action.find('.woopt_hide').hide();
      $action.find('.woopt_show_if_' + show_field_action).show();
    } else {
      $('.woopt_action_td').each(function() {
        var show_field_action = $(this).
            find('.woopt_action_selector').
            find(':selected').
            data('show');

        $(this).find('.woopt_hide').hide();
        $(this).find('.woopt_show_if_' + show_field_action).show();
      });
    }
  }

  function woopt_show_conditional($current_conditional) {
    if (typeof $current_conditional !== 'undefined') {
      var show_field_conditional = $current_conditional.find(
          '.woopt_conditional').find(':selected').data('show');

      $current_conditional.find('.woopt_hide').hide();
      $current_conditional.find('.woopt_show_if_' + show_field_conditional).
          show();
    } else {
      $('.woopt_conditional_item').each(function() {
        var show_field_conditional = $(this).
            find('.woopt_conditional').
            find(':selected').
            data('show');

        $(this).find('.woopt_hide').hide();
        $(this).find('.woopt_show_if_' + show_field_conditional).show();
      });
    }
  }

  function woopt_show_apply($action) {
    if (typeof $action !== 'undefined') {
      var apply = $action.find('.woopt_apply_selector').find(':selected').val();
      var apply_text = $action.find('.woopt_apply_selector').
          find(':selected').
          text();

      $action.find('.woopt_apply_text').text(apply_text);
      $action.find('.hide_apply').hide();
      $action.find('.show_if_' + apply).show();
      $action.find('.show_apply').show();
      $action.find('.hide_if_' + apply).hide();
    } else {
      $('.woopt_action').each(function() {
        var $action = $(this);
        var apply = $action.find('.woopt_apply_selector').
            find(':selected').
            val();
        var apply_text = $action.find('.woopt_apply_selector').
            find(':selected').
            text();

        $action.find('.woopt_apply_text').text(apply_text);
        $action.find('.hide_apply').hide();
        $action.find('.show_if_' + apply).show();
        $action.find('.show_apply').show();
        $action.find('.hide_if_' + apply).hide();
      });
    }
  }

  function woopt_sortable() {
    $('.woopt_actions').sortable({handle: '.woopt_action_move'});
  }

  function woopt_build_value() {
    $('.woopt_action').each(function() {
      var $this = $(this);
      var action = '';
      var conditional = '';
      var conditional_arr = [];
      var roles = $this.find('.woopt_user_roles_select option:selected').
          toArray().
          map(item => item.value).
          join();

      $this.find('.woopt_conditional_item').each(function() {
        var $this_conditional = $(this);
        var current_conditional = $(this).find('.woopt_conditional').val();
        switch (current_conditional) {
          case 'date_range':
            conditional_arr.push(current_conditional + '>' +
                $this_conditional.find('.woopt_date_range').val());
            break;
          case 'date_multi':
            conditional_arr.push(current_conditional + '>' +
                $this_conditional.find('.woopt_date_multi').val());
            break;
          case 'date_on':
            conditional_arr.push(current_conditional + '>' +
                $this_conditional.find('.woopt_date').val());
            break;
          case 'date_before':
            conditional_arr.push(current_conditional + '>' +
                $this_conditional.find('.woopt_date').val());
            break;
          case 'date_after':
            conditional_arr.push(current_conditional + '>' +
                $this_conditional.find('.woopt_date').val());
            break;
          case 'date_time_before':
            conditional_arr.push(current_conditional + '>' +
                $this_conditional.find('.woopt_date_time').val());
            break;
          case 'date_time_after':
            conditional_arr.push(current_conditional + '>' +
                $this_conditional.find('.woopt_date_time').val());
            break;
          case 'date_even':
            conditional_arr.push(current_conditional + '>true');
            break;
          case 'date_odd':
            conditional_arr.push(current_conditional + '>true');
            break;
          case 'time_range':
            conditional_arr.push(current_conditional + '>' +
                $this_conditional.find('.woopt_time_start').val() + ' - ' +
                $this_conditional.find('.woopt_time_end').val());
            break;
          case 'time_before':
            conditional_arr.push(current_conditional + '>' +
                $this_conditional.find('.woopt_time_on').val());
            break;
          case 'time_after':
            conditional_arr.push(current_conditional + '>' +
                $this_conditional.find('.woopt_time_on').val());
            break;
          case 'weekly_every':
            conditional_arr.push(current_conditional + '>' +
                $this_conditional.find('.woopt_weekday').val());
            break;
          case 'week_even':
            conditional_arr.push(current_conditional + '>true');
            break;
          case 'week_odd':
            conditional_arr.push(current_conditional + '>true');
            break;
          case 'week_no':
            conditional_arr.push(current_conditional + '>' +
                $this_conditional.find('.woopt_weekno').val());
            break;
          case 'monthly_every':
            conditional_arr.push(current_conditional + '>' +
                $this_conditional.find('.woopt_monthday').val());
            break;
          case 'month_no':
            conditional_arr.push(current_conditional + '>' +
                $this_conditional.find('.woopt_monthno').val());
            break;
          case 'days_less_published':
            conditional_arr.push(current_conditional + '>' +
                $this_conditional.find('.woopt_number').val());
            break;
          case 'days_greater_published':
            conditional_arr.push(current_conditional + '>' +
                $this_conditional.find('.woopt_number').val());
            break;
          case 'every_day':
            conditional_arr.push(current_conditional + '>true');
            break;
        }
      });

      conditional = conditional_arr.join('&');

      if ($this.find('.woopt_apply_selector').length) {
        action = $this.find('.woopt_apply_selector').val() + '|' +
            $this.find('.woopt_apply_val').val() + '|' +
            $this.find('.woopt_action_selector').val() + '|' +
            $this.find('.woopt_price').val() + '|' + conditional + '|' + roles;
      } else {
        action = $this.find('.woopt_action_selector').val() + '|' +
            $this.find('.woopt_price').val() + '|' + conditional + '|' + roles;
      }

      $this.find('.woopt_action_val').val(action);
    });
  }

  function woopt_build_apply_combination() {
    $('.woopt_action').each(function() {
      var $action = $(this);
      var conditional = '';
      var conditional_arr = [];

      if ($action.find('.woopt_apply_selector').val() === 'apply_combination') {
        $action.find('.woopt_apply_conditional').each(function() {
          var apply_conditional_select = $(this).
              find('.woopt_apply_conditional_select').
              val();
          var apply_conditional_val = $(this).
              find('.woopt_apply_conditional_val').
              val();

          conditional_arr.push(
              apply_conditional_select + '>' + apply_conditional_val);
        });

        conditional = conditional_arr.join('&');
        $action.find('.woopt_apply_val').val(conditional).trigger('change');
      }
    });
  }

  function woopt_build_label($select) {
    if (typeof $select !== 'undefined') {
      var label = $select.find('option:selected').text();

      if ($select.closest('.woopt_action').
          find('.woopt_apply_selector').length) {
        var apply = $select.closest('.woopt_action').
            find('.woopt_apply_selector').
            val();
        var apply_for = $select.closest('.woopt_action').
            find('.woopt_apply_selector option:selected').
            text();
        var apply_val = $select.closest('.woopt_action').
            find('.woopt_apply_val').
            val();

        if (apply !== 'apply_all' && apply !== 'apply_variation' && apply !==
            'apply_not_variation' && apply !== 'apply_category' && apply !==
            'apply_tag' && apply !== 'apply_combination' && apply_val !== '') {
          label += ' <span>' + apply_for + ': ' + apply_val + '</span>';
        } else {
          label += ' <span>' + apply_for + '</span>';
        }
      }

      $select.closest('.woopt_action').find('.woopt_action_label').html(label);
    } else {
      $('.woopt_action_selector').each(function() {
        var $this = $(this);
        var label = $this.find('option:selected').text();

        if ($this.closest('.woopt_action').
            find('.woopt_apply_selector').length) {
          var apply = $this.closest('.woopt_action').
              find('.woopt_apply_selector').
              val();
          var apply_for = $this.closest('.woopt_action').
              find('.woopt_apply_selector option:selected').
              text();
          var apply_val = $this.closest('.woopt_action').
              find('.woopt_apply_val').
              val();

          if (apply !== 'apply_all' && apply !== 'apply_variation' && apply !==
              'apply_not_variation' && apply !== 'apply_category' && apply !==
              'apply_tag' && apply !== 'apply_combination' && apply_val !==
              '') {
            label += ' <span>' + apply_for + ': ' + apply_val + '</span>';
          } else {
            label += ' <span>' + apply_for + '</span>';
          }
        }

        $this.closest('.woopt_action').find('.woopt_action_label').html(label);
      });
    }
  }
})(jQuery);