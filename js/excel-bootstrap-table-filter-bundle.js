(function ($$1) {
'use strict';

$$1 = 'default' in $$1 ? $$1['default'] : $$1;

var FilterMenu = function () {
    function FilterMenu(target, th, column, index, options) {
        this.options = options;
        this.th = th;
        this.afterTarget = $(th).find('div.columntitle').first();
        this.column = column;
        this.index = index;
        this.tds = target.find('tbody tr td:nth-child(' + (this.column + 1) + ')').toArray();
    }
    FilterMenu.prototype.initialize = function () {
        this.menu = this.dropdownFilterDropdown();
        //this.th.appendChild(this.menu);
        this.afterTarget.after(this.menu);
        var $trigger = $(this.menu.children[0]);
        var $content = $(this.menu.children[1]);
        var $menu = $(this.menu);
        $trigger.click(function () {
            return $content.toggle();
        });
        $(document).click(function (el) {
            if (!$menu.is(el.target) && $menu.has(el.target).length === 0) {
                $content.hide();
            }
        });
    };
    FilterMenu.prototype.dropdownFilterItem = function (td, self, options) {
        var value = td.innerText;
        var cleanTd = $(td).clone();
        cleanTd.find('.filetags-wrap').remove();
        var cleanValue = cleanTd.text().trim();
        if(cleanTd.find('select, input:not(.fileselect)').length>0){
         cleanValue = cleanTd.find('select, input:not(.fileselect)').first().val().trim();
         //alert(cleanTd.find('select').first().val().toSource());
       }
        var dropdownFilterItem = document.createElement('div');
        dropdownFilterItem.className = 'dropdown-filter-item';
        var input = document.createElement('input');
        input.type = 'checkbox';
        input.value = cleanValue.trim().replace(/ +(?= )/g, '');

        var column = cleanTd.attr('column');
        if( typeof options.hide_column_key_values!='undefined' &&
          typeof options.hide_column_key_values[column]!='undefined' &&
            options.hide_column_key_values[column]===cleanValue){
        }
        else{
          input.setAttribute('checked', 'checked');
        }

        input.className = 'dropdown-filter-menu-item item';
        input.setAttribute('data-column', self.column.toString());
        input.setAttribute('data-index', self.index.toString());
        dropdownFilterItem.appendChild(input);
        dropdownFilterItem.innerHTML = dropdownFilterItem.innerHTML.trim() + ' ' + cleanValue;
        return dropdownFilterItem;
    };
    FilterMenu.prototype.dropdownFilterSort = function (direction) {
        //var dropdownFilterItem = document.createElement('div');
        //dropdownFilterItem.className = 'dropdown-filter-sort';
        var span = document.createElement('span');
        span.className = 'dropdown-filter-sort';
        span.setAttribute('data-order', direction.toLowerCase().split(' ').join('-'));
        span.setAttribute('data-column', this.column.toString());
        span.setAttribute('data-index', this.index.toString());
        span.innerText = direction;
        //dropdownFilterItem.appendChild(span);
       // return dropdownFilterItem;
        return span;
    };
    FilterMenu.prototype.dropdownFilterContent = function () {
        var _this = this;
        var self = this;
        var dropdownFilterContent = document.createElement('div');
        dropdownFilterContent.className = 'dropdown-filter-content';
        var innerDivs = this.tds.reduce(function (arr, el) {
            var values = arr.map(function (el) {
             if($(el).find('select, input:not(.fileselect)').length>0){
              return $(el).find('select, input:not(.fileselect)').first().val().trim();
             }
             return el.innerText.trim();
            });
            //if (values.indexOf(el.innerText.trim()) < 0)arr.push(el);
            var elVal = el.innerText.trim();
            if($(el).find('select, input:not(.fileselect)').length>0){
            	elVal = $(el).find('select, input:not(.fileselect)').first().val().trim();
             }
            //alert(values.toSource()+':'+elVal);
            if(/*el.innerText.trim().length>0 && */values.indexOf(elVal) < 0){
            	arr.push(el);
            }
            return arr;
        }, []).sort(function (a, b) {
            var A = a.innerText.toLowerCase();
            var B = b.innerText.toLowerCase();
            if($(a).find('select, input:not(.fileselect)').length>0){
            	A = $(a).find('select, input:not(.fileselect)').first().val().trim();
             }
            if($(b).find('select, input:not(.fileselect)').length>0){
            	B = $(b).find('select, input:not(.fileselect)').first().val().trim();
             }
            if (!isNaN(Number(A)) && !isNaN(Number(B))) {
                if (Number(A) < Number(B)) return -1;
                if (Number(A) > Number(B)) return 1;
            } else {
                if (A < B) return -1;
                if (A > B) return 1;
            }
            return 0;
        }).map(function (td) {
            return _this.dropdownFilterItem(td, self, self.options);
        });
        this.inputs = innerDivs.map(function (div) {
            return div.firstElementChild;
        });
        var outerDiv = innerDivs.reduce(function (outerDiv, innerDiv) {
            outerDiv.appendChild(innerDiv);
            return outerDiv;
        }, document.createElement('div'));
        outerDiv.className = 'checkbox-container';
        var elements = [];
        if (this.options.sort) elements = elements.concat([this.dropdownFilterSort(this.options.captions.a_to_z),
        	this.dropdownFilterSort(this.options.captions.z_to_a)]);
        return elements.concat(outerDiv).reduce(function (html, el) {
            html.appendChild(el);
            return html;
        }, dropdownFilterContent);
    };
    FilterMenu.prototype.dropdownFilterDropdown = function () {
        var dropdownFilterDropdown = document.createElement('div');
        dropdownFilterDropdown.className = 'dropdown-filter-dropdown';
        var icon = document.createElement('i');
        icon.className = 'arrow-down';
        dropdownFilterDropdown.appendChild(icon);
        dropdownFilterDropdown.appendChild(this.dropdownFilterContent());
        if ($(this.th).hasClass('no-sort')) {
            $(dropdownFilterDropdown).find('.dropdown-filter-sort').remove();
        }
        if ($(this.th).hasClass('no-filter')) {
            $(dropdownFilterDropdown).find('.checkbox-container').remove();
        }
        return dropdownFilterDropdown;
    };
    return FilterMenu;
}();

var FilterCollection = function () {
    function FilterCollection(target, options) {
        this.target = target;
        this.options = options;
        this.ths = target.find('th' + options.columnSelector).toArray();
        this.filterMenus = this.ths.map(function (th, index) {
            var column = $(th).index();
            return new FilterMenu(target, th, column, index, options);
        });
        this.rows = target.find('tbody').find('tr').toArray();
        this.table = target.get(0);
    }
    FilterCollection.prototype.initialize = function () {
        this.filterMenus.forEach(function (filterMenu) {
            filterMenu.initialize();
        });
        this.bindCheckboxes();
        this.bindSort();
    };
    FilterCollection.prototype.bindCheckboxes = function () {
        var filterMenus = this.filterMenus;
        var rows = this.rows;
        var ths = this.ths;
        var updateRowVisibility = this.updateRowVisibility;
        this.target.find('.dropdown-filter-menu-item.item').change(function () {
            var index = $(this).data('index');
            var value = $(this).val();
            updateRowVisibility(filterMenus, rows, ths);
        });
    };
    FilterCollection.prototype.bindSort = function () {
        var filterMenus = this.filterMenus;
        var rows = this.rows;
        var ths = this.ths;
        var sort = this.sort;
        var table = this.table;
        var options = this.options;
        var updateRowVisibility = this.updateRowVisibility;
        this.target.find('.dropdown-filter-sort').click(function () {
            var $sortElement = $(this);
            var column = $sortElement.data('column');
            var order = $sortElement.data('order');
            sort(column, order, table, options);
            updateRowVisibility(filterMenus, rows, ths);
        });
    };
    FilterCollection.prototype.updateRowVisibility = function (filterMenus, rows, ths) {
        var showRows = rows;
        var selectedLists = filterMenus.map(function (filterMenu) {
            return {
                column: filterMenu.column,
                selected: filterMenu.inputs.filter(function (input) {
                    return input.checked;
                }).map(function (input) {
                    return input.value.trim().replace(/ +(?= )/g, '');
                })
            };
        });
        for (var i = 0; i < rows.length; i++) {
            var tds = rows[i].children;
            for (var j = 0; j < selectedLists.length; j++) {
                var cleanTd = $(tds[selectedLists[j].column]).clone();
                cleanTd.find('.filetags-wrap').remove();
                var content = cleanTd.text().trim().replace(/ +(?= )/g, '');
                if(cleanTd.find('select, input:not(.fileselect)').length>0){
                	content = cleanTd.find('select, input:not(.fileselect)').first().val().trim();
                }
                //alert(content+':'+selectedLists[j].toSource());
                if (selectedLists[j].selected.indexOf(content) === -1) {
                    $(rows[i]).hide();
                    break;
                }
                $(rows[i]).show();
            }
        }
    };
    FilterCollection.prototype.sort = function (column, order, table, options) {
        var flip = 1;
        if (order === options.captions.z_to_a.toLowerCase().split(' ').join('-')) flip = -1;
        var tbody = $(table).find('tbody').get(0);
        var rows = $(tbody).find('tr').get();
        rows.sort(function (a, b) {
            var A = a.children[column].innerText.toUpperCase();
            var B = b.children[column].innerText.toUpperCase();
            if($(a.children[column]).find('select, input:not(.fileselect)').length>0){
            	A = $(a.children[column]).find('select, input:not(.fileselect)').first().val().trim();
             }
            if($(a.children[column]).find('select, input:not(.fileselect)').length>0){
            	B = $(b.children[column]).find('select, input:not(.fileselect)').first().val().trim();
             }
            if (!isNaN(Number(A)) && !isNaN(Number(B))) {
                if (Number(A) < Number(B)) return -1 * flip;
                if (Number(A) > Number(B)) return 1 * flip;
            } else {
                if (A < B) return -1 * flip;
                if (A > B) return 1 * flip;
            }
            return 0;
        });
        for (var i = 0; i < rows.length; i++) {
            tbody.appendChild(rows[i]);
        }
    };
    return FilterCollection;
}();

$$1.fn.excelTableFilter = function (options) {
    var target = this;
    options = $$1.extend({}, $$1.fn.excelTableFilter.options, options);
    if (typeof options.columnSelector === 'undefined') options.columnSelector = '';
    if (typeof options.sort === 'undefined') options.sort = true;
    if (typeof options.captions === 'undefined') options.captions = {
        a_to_z: "\u2191",
        z_to_a: "\u2193"
    };
    var filterCollection = new FilterCollection(target, options);
    filterCollection.initialize();
    filterCollection.updateRowVisibility(filterCollection.filterMenus, filterCollection.rows, filterCollection.ths);
    return target;
};
$$1.fn.excelTableFilter.options = {};

}(jQuery));
//# sourceMappingURL=excel-bootstrap-table-filter-bundle.js.map
