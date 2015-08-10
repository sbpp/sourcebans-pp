// if xajax has not yet been initialized, wait a second and try again
// once xajax has been initialized, then install the table command
// handlers.			
installTableUpdater = function() {
	var xjxReady = false;
	try {
		if (xajax) xjxReady = true;
	} catch (e) {
	}
	if (false == xjxReady) {
		setTimeout('installTableUpdater();', 1000);
		return;
	}

	try {
		if ('undefined' == typeof xajax.ext.tables)
			xajax.ext.tables = {};
	} catch (e) {
		xajax.ext = {};
		xajax.ext.tables = {};
	}

	// internal helper functions
	xajax.ext.tables.internal = {};
	xajax.ext.tables.internal.createTable = function(table) {
		if ('string' != typeof (table))
			throw { name: 'TableError', message: 'Invalid table name specified.' }
		var newTable = document.createElement('table');
		newTable.id = table;
		// save the column configuration
		xajax.ext.tables.appendHeader(table + '_header', newTable);
		xajax.ext.tables.appendBody(table + '_body', newTable);
		xajax.ext.tables.appendFooter(table + '_footer', newTable);
		return newTable;
	}
	xajax.ext.tables.internal.createRow = function(objects, id) {
		var row = document.createElement('tr');
		if (null != id)
			row.id = id;
		return row;
	}
	xajax.ext.tables.internal.createCell = function(objects, id) {
		var cell = document.createElement('td');
		if (null != id)
			cell.id = id;
		cell.innerHTML = '...';
		return cell;
	}
	xajax.ext.tables.internal.getColumnNumber = function(objects, cell) {
		var position;
		var columns = objects.header.getElementsByTagName('td');
		for (var column = 0; column < columns.length; ++column)
			if (columns[column].id == cell)
				return column;
		throw { name: 'TableError', message: 'Column not found. (getColumnNumber)' }
		return undefined;
	}
	xajax.ext.tables.internal.objectify = function(params, required) {
		if ('undefined' == typeof params.source)
			return false;
		var source = params.source;
		if ('string' == typeof (source))
			source = xajax.$(source);
		if ('TBODY' == source.nodeName) {
			params.table = source.parentNode;
		} else if ('TABLE' == source.nodeName) {
			params.table = source;
		} else if ('TR' == source.nodeName) {
			params.row = source;
			params.body = source.parentNode;
			params.table = source.parentNode.parentNode;
		} else if ('TD' == source.nodeName) {
			params.cell = source;
			params.row = source.parentNode;
			params.columns = params.row.getElementsByTagName('TD');
			for (var column = 0; 'undefined' == typeof params.column && column < params.columns.length; ++column)
				if (params.cell.id == params.columns[column].id)
					params.column = column;
			params.table = source.parentNode.parentNode.parentNode;
		} else if ('THEAD' == source.nodeName) {
			params.table = source.parentNode;
		} else if ('TFOOT' == source.nodeName) {
			params.table = source.parentNode;
		} else
			params.source = source;
		
		var bodies = params.table.getElementsByTagName('TBODY');
		if (0 < bodies.length)
			params.body = bodies[0];
		var headers = params.table.getElementsByTagName('THEAD');
		if (0 < headers.length)
			params.header = headers[0];
		var feet = params.table.getElementsByTagName('TFOOT');
		if (0 < feet.length)
			params.footer = feet[0];
		if ('undefined' != typeof params.body)
			params.rows = params.body.getElementsByTagName('TR');
		if ('undefined' != typeof params.row)
			params.cells = params.row.getElementsByTagName('TD');
		if ('undefined' != typeof params.header)
			params.columns = params.header.getElementsByTagName('TD');
		
		if ('undefined' == typeof required)
			return true;
		
		for (var index = 0; index < required.length; ++index) {
			var require = required[index];
			var is_defined = false;
			eval('is_defined = (undefined != params.' + require + ');');
			if (false == is_defined)
				throw { name: 'TableError', message: 'Unable to locate required object [' + require + '].' };
		}
		
		return true;
	}
	// table
	xajax.ext.tables.append = function(table, parent) {
		if ('string' == typeof (parent))
			parent = xajax.$(parent);
		parent.appendChild(xajax.ext.tables.internal.createTable(table));
	}
	xajax.ext.tables.insert = function(table, parent, before) {
		if ('string' == typeof (parent))
			parent = xajax.$(parent);
		if ('string' == typeof (before))
			before = xajax.$(before);
		parent.insertBefore(xajax.ext.tables.internal.createTable(table), before);
	}
	xajax.ext.tables.remove = function(table) {
		var objects = { source: table };
		xajax.ext.tables.internal.objectify(objects, ['table']);
		objects.table.parentNode.removeChild(objects.table);
	}
	xajax.ext.tables.appendHeader = function(id, table) {
		var objects = { source: table };
		xajax.ext.tables.internal.objectify(objects, ['table']);
		if ('undefined' == typeof objects.header) {
			var thead = document.createElement('thead');
			if (null != id)
				thead.id = id;
			objects.header = thead;
			thead.appendChild(xajax.ext.tables.internal.createRow(objects, null));
			if ('undefined' == typeof objects.table.firstChild)
				table.appendChild(thead);
			else
				table.insertBefore(thead, table.firstChild);
		}
	}
	xajax.ext.tables.appendBody = function(id, table) {
		var objects = { source: table };
		xajax.ext.tables.internal.objectify(objects, ['table']);
		if ('undefined' == typeof objects.body) {
			var tbody = document.createElement('tbody');
			if (null != id)
				tbody.id = id;
			objects.body = tbody;
		}
		if ('undefined' != typeof objects.rows) {
			for (var rn = 0; rn < objects.rows.length; ++rn) {
				var row = objects.rows[rn];
				objects.table.removeChild(row);
				objects.body.appendChild(row);
			}
		}
		if ('undefined' != typeof objects.footer)
			objects.table.insertBefore(objects.body, objects.footer);
		else
			objects.table.appendChild(objects.body);
	}
	xajax.ext.tables.appendFooter = function(id, table) {
		var objects = { source: table }
		xajax.ext.tables.internal.objectify(objects, ['table']);
		if ('undefined' == typeof objects.footer) {
			var tfoot = document.createElement('tfoot');
			if (null != id)
				tfoot.id = id;
			objects.footer = tfoot;
			tfoot.appendChild(xajax.ext.tables.internal.createRow(objects, null));
			objects.table.appendChild(tfoot);
		}
	}
	// rows
	xajax.ext.tables.rows = {}
	xajax.ext.tables.rows.internal = {}
	xajax.ext.tables.rows.internal.calculateRow = function(objects, position) {
		if ('undefined' == typeof position)
			throw { name: 'TableError', message: 'Missing row number / id.' }
		if ('undefined' == typeof objects.row)
			if ('undefined' != typeof objects.rows)
				if ('undefined' != typeof objects.rows[position])
					objects.row = objects.rows[position];
		if ('undefined' == typeof objects.row)
			objects.row = xajax.$(position);
		if ('undefined' == typeof objects.row)
			throw { name: 'TableError', message: 'Invalid row number / row id specified.' }
	}
	xajax.ext.tables.rows.append = function(id, table) {
		var objects = { source: table }
		xajax.ext.tables.internal.objectify(objects, ['table', 'body']);
		var row = xajax.ext.tables.internal.createRow(objects, id);
		if ('undefined' != typeof objects.columns) {
			for (var column = 0; column < objects.columns.length; ++column) {
				var cell = xajax.ext.tables.internal.createCell(objects, null);
				cell.innerHTML = '...';
				row.appendChild(cell);
			}
		}
		objects.body.appendChild(row);
	}
	xajax.ext.tables.rows.insert = function(id, source, position) {
		var objects = { source: source }
		xajax.ext.tables.internal.objectify(objects, ['table', 'body']);
		if ('undefined' == typeof objects.row)
			xajax.ext.tables.rows.internal.calculateRow(objects, position);
		var row = xajax.ext.tables.internal.createRow(objects, id);
		if ('undefined' != typeof objects.columns) {
			for (var column = 0; column < objects.columns.length; ++column) {
				var cell = xajax.ext.tables.internal.createCell(objects, null);
				cell.innerHTML = '...';
				row.appendChild(cell);
			}
		}
		objects.body.insertBefore(row, objects.row);
	}
	xajax.ext.tables.rows.replace = function(id, source, position) {
		var objects = { source: source }
		xajax.ext.tables.internal.objectify(objects, ['table', 'body']);
		if ('undefined' == typeof objects.row)
			xajax.ext.tables.rows.internal.calculateRow(objects, position);
		var row = xajax.ext.tables.internal.createRow(objects, id);
		if ('undefined' != typeof objects.columns) {
			for (var column = 0; column < objects.columns.length; ++column) {
				var cell = xajax.ext.tables.internal.createCell(objects, null);
				cell.innerHTML = '...';
				row.appendChild(cell);
			}
		}
		objects.body.insertBefore(row, objects.row);
		objects.body.removeChild(objects.row);
	}
	xajax.ext.tables.rows.remove = function(source, position) {
		var objects = { source: source }
		xajax.ext.tables.internal.objectify(objects, ['table', 'body']);
		if ('undefined' == typeof objects.row)
			xajax.ext.tables.rows.internal.calculateRow(objects, position);
		objects.body.removeChild(objects.row);
	}
	xajax.ext.tables.rows.assignProperty = function(value, source, position, property) {
		var objects = { source: source }
		xajax.ext.tables.internal.objectify(objects, ['table', 'body', 'header']);
		if ('undefined' == typeof objects.row)
			xajax.ext.tables.rows.internal.calculateRow(objects, position);
		if ('undefined' != typeof property)
			eval('objects.row.' + property + ' = value;');
	}
	xajax.ext.tables.rows.assign = function(values, source, position, start_column) {
		var objects = { source: source }
		xajax.ext.tables.internal.objectify(objects, ['table', 'body', 'header']);
		if ('undefined' == typeof objects.row)
			xajax.ext.tables.rows.internal.calculateRow(objects, position);
		if ('undefined' == typeof start_column)
			start_column = 0;
		for (var column = 0; column < values.length; ++column)
			xajax.ext.tables.cells.assign(values[column], objects.row, start_column + column);
	}
	// columns
	xajax.ext.tables.columns = {}
	xajax.ext.tables.columns.internal = {}
	xajax.ext.tables.columns.internal.calculateColumn = function(objects, position) {
		if ('undefined' == typeof position)
			throw { name: 'TableError', message: 'Missing column number / id.' }
		if ('undefined' == typeof objects.column)
			if ('undefined' != typeof objects.columns)
				if ('undefined' != typeof objects.columns[position])
					objects.column = position;
		if ('undefined' == typeof objects.column)
			for (var column = 0; 'undefined' == typeof objects.column && column < objects.columns.length; ++column)
				if (objects.columns[column].id == position)
					objects.column = column;
		if ('undefined' == typeof objects.column)
			throw { name: 'TableError', message: 'Invalid column number / row id specified.' }
	}
	xajax.ext.tables.columns.append = function(column_definition, table) {
		var objects = { source: table }
		xajax.ext.tables.internal.objectify(objects, ['table', 'header', 'body']);
		var cell = xajax.ext.tables.internal.createCell(objects, column_definition.id);
		if ('undefined' != typeof column_definition.name)
			cell.innerHTML = column_definition.name;
		objects.header.firstChild.appendChild(cell);
		if ('undefined' != typeof objects.rows)
			for (var i = 0; i < objects.rows.length; ++i)
				xajax.ext.tables.cells.append({id: null}, objects.rows[i]);
	}
	xajax.ext.tables.columns.insert = function(column_definition, source, position) {
		var objects = { source: source }
		xajax.ext.tables.internal.objectify(objects, ['table', 'header']);
		if ('undefined' == typeof objects.column)
			xajax.ext.tables.columns.internal.calculateColumn(objects, position);
		var column = xajax.ext.tables.internal.createCell(objects, column_definition.id);
		if ('undefined' != typeof column_definition.name)
			column.innerHTML = column_definition.name;
		objects.header.firstChild.insertBefore(column, objects.columns[objects.column]);
		if ('undefined' != typeof objects.rows)
			for (var i = 0; i < objects.rows.length; ++i)
				xajax.ext.tables.cells.insert({id: null}, objects.rows[i], objects.column);
	}
	xajax.ext.tables.columns.replace = function(column_definition, source, position) {
		var objects = { source: source }
		xajax.ext.tables.internal.objectify(objects, ['table', 'header', 'columns']);
		if ('undefined' == typeof objects.column)
			xajax.ext.tables.columns.internal.calculateColumn(objects, position);
		var before = objects.columns[objects.column];
		var column = xajax.ext.tables.internal.createCell(objects, column_definition.id);
		if ('undefined' != typeof column_definition.name)
			column.innerHTML = column_definition.name;
		objects.header.firstChild.insertBefore(column, before);
		objects.header.firstChild.removeChild(before);
		if ('undefined' != typeof objects.rows)
			for (var i = 0; i < objects.rows.length; ++i)
				xajax.ext.tables.cells.replace({id: null}, objects.rows[i], objects.column);
	}
	xajax.ext.tables.columns.remove = function(source, position) {
		var objects = { source: source }
		xajax.ext.tables.internal.objectify(objects, ['table', 'header']);
		if ('undefined' == typeof objects.column)
			xajax.ext.tables.columns.internal.calculateColumn(objects, position);
		objects.header.firstChild.removeChild(objects.columns[objects.column]);
		if ('undefined' != typeof objects.rows)
			for (var i = 0; i < objects.rows.length; ++i)
				xajax.ext.tables.cells.remove(objects.rows[i], objects.column);
	}
	xajax.ext.tables.columns.assign = function(values, source, position, start_row) {
		var objects = { source: source }
		xajax.ext.tables.internal.objectify(objects, ['table', 'cell']);
		if ('undefined' == typeof objects.column)
			xajax.ext.tables.columns.internal.calculateColumn(objects, position);
		for (var row = 0; row < values.length; ++row)
			xajax.ext.tables.cells.assign(values[row], objects.rows[start_row + row], objects.column);
	}
	xajax.ext.tables.columns.assignProperty = function(value, source, position, property) {
		var objects = { source: source }
		xajax.ext.tables.internal.objectify(objects, ['table', 'cell']);
		if ('undefined' == typeof objects.column)
			xajax.ext.tables.columns.internal.calculateColumn(objects, position);
		for (var row = 0; row < objects.rows.length; ++row)
			xajax.ext.tables.cells.assignProperty(value, objects.rows[row], objects.column, property);
	}
	// cells
	xajax.ext.tables.cells = {}
	xajax.ext.tables.cells.internal = {}
	xajax.ext.tables.cells.internal.calculateCell = function(objects, position) {
		if ('undefined' == typeof position)
			throw { name: 'TableError', message: 'Missing cell number / id.' }
		if ('undefined' == typeof objects.cell)
			if ('undefined' != typeof objects.cells)
				if ('undefined' != typeof objects.cells[position])
					objects.cell = objects.cells[position];
		if ('undefined' == typeof objects.cell)
			if ('undefined' != typeof objects.columns)
				for (var column = 0; 'undefined' == typeof objects.cell && column < objects.columns.length; ++column)
					if (objects.columns[column].id == position)
						objects.cell = objects.cells[column];
		if ('undefined' == typeof objects.cell)
			throw { name: 'TableError', message: 'Invalid cell number / id specified.' }
	}
	xajax.ext.tables.cells.append = function(cell_definition, source) {
		var objects = { source: source }
		xajax.ext.tables.internal.objectify(objects, ['table', 'row']);
		var cell = xajax.ext.tables.internal.createCell(objects, cell_definition.id);
		if ('undefined' != typeof cell_definition.name)
			cell.innerHTML = cell_definition.name;
		objects.row.appendChild(cell);
	}
	xajax.ext.tables.cells.insert = function(cell_definition, source, position) {
		var objects = { source: source }
		xajax.ext.tables.internal.objectify(objects, ['table', 'row']);
		if ('undefined' == typeof objects.cell)
			xajax.ext.tables.cells.internal.calculateCell(objects, position);
		var cell = xajax.ext.tables.internal.createCell(objects, cell_definition.id);
		if ('undefined' != typeof cell_definition.name)
			cell.innerHTML = cell_definition.name;
		objects.row.insertBefore(cell, objects.cell);
	}
	xajax.ext.tables.cells.replace = function(cell_definition, source, position) {
		var objects = { source: source }
		xajax.ext.tables.internal.objectify(objects, ['table', 'row']);
		if ('undefined' == typeof objects.cell)
			xajax.ext.tables.cells.internal.calculateCell(objects, position);
		var cell = xajax.ext.tables.internal.createCell(objects, cell_definition.id);
		if ('undefined' != typeof cell_definition.name)
			cell.innerHTML = cell_definition.name;
		objects.row.insertBefore(cell, objects.cell);
		objects.row.removeChild(objects.cell);
	}
	xajax.ext.tables.cells.remove = function(source, position) {
		var objects = { source: source }
		xajax.ext.tables.internal.objectify(objects, ['table', 'row']);
		if ('undefined' == typeof objects.cell)
			xajax.ext.tables.cells.internal.calculateCell(objects, position);
		objects.row.removeChild(objects.cell);
	}
	xajax.ext.tables.cells.assign = function(value, source, position) {
		var objects = { source: source }
		xajax.ext.tables.internal.objectify(objects, ['table', 'row']);
		if ('undefined' == typeof objects.cell)
			xajax.ext.tables.cells.internal.calculateCell(objects, position);
		objects.cell.innerHTML = value;
	}
	xajax.ext.tables.cells.assignProperty = function(value, source, position, property) {
		var objects = { source: source }
		xajax.ext.tables.internal.objectify(objects, ['table', 'row']);
		if ('undefined' == typeof objects.cell)
			xajax.ext.tables.cells.internal.calculateCell(objects, position);
		eval('objects.cell.' + property + ' = value;');
	}

	// command handlers

	// tables
	xajax.command.handler.register('et_at', function(args) {
		args.fullName = 'ext.tables.append';
		xajax.ext.tables.append(args.data, args.id);
		return true;
	});
	xajax.command.handler.register('et_it', function(args) {
		args.fullName = 'ext.tables.insert';
		xajax.ext.tables.insert(args.data, args.id, args.pos);
		return true;
	});
	xajax.command.handler.register('et_dt', function(args) {
		args.fullName = 'ext.tables.remove';
		xajax.ext.tables.remove(args.data);
		return true;
	});
	// rows
	xajax.command.handler.register('et_ar', function(args) {
		args.fullName = 'ext.tables.rows.append';
		xajax.ext.tables.rows.append(args.data, args.id);
		return true;
	});
	xajax.command.handler.register('et_ir', function(args) {
		args.fullName = 'ext.tables.rows.insert';
		xajax.ext.tables.rows.insert(args.data, args.id, args.pos);
		return true;
	});
	xajax.command.handler.register('et_rr', function(args) {
		args.fullName = 'ext.tables.rows.replace';
		xajax.ext.tables.rows.replace(args.data, args.id, args.pos);
		return true;
	});
	xajax.command.handler.register('et_dr', function(args) {
		args.fullName = 'ext.tables.rows.remove';
		xajax.ext.tables.rows.remove(args.id, args.pos);
		return true;
	});
	xajax.command.handler.register('et_asr', function(args) {
		args.fullName = 'ext.tables.rows.assign';
		xajax.ext.tables.rows.assign(args.data, args.id, args.pos);
		return true;
	});
	xajax.command.handler.register('et_asrp', function(args) {
		args.fullName = 'ext.tables.rows.assignProperty';
		xajax.ext.tables.rows.assignProperty(args.data, args.id, args.pos, args.prop);
	});
	// columns
	xajax.command.handler.register('et_acol', function(args) {
		args.fullName = 'ext.tables.columns.append';
		xajax.ext.tables.columns.append(args.data, args.id);
		return true;
	});
	xajax.command.handler.register('et_icol', function(args) {
		args.fullName = 'ext.tables.columns.insert';
		xajax.ext.tables.columns.insert(args.data, args.id, args.pos);
		return true;
	});
	xajax.command.handler.register('et_rcol', function(args) {
		args.fullName = 'ext.tables.columns.replace';
		xajax.ext.tables.columns.replace(args.data, args.id, args.pos);
		return true;
	});
	xajax.command.handler.register('et_dcol', function(args) {
		args.fullName = 'ext.tables.columns.remove';
		xajax.ext.tables.columns.remove(args.id, args.pos);
		return true;
	});
	xajax.command.handler.register('et_ascol', function(args) {
		args.fullName = 'ext.tables.columns.assign';
		xajax.ext.tables.columns.assign(args.data, args.id, args.pos, args.type);
		return true;
	});
	xajax.command.handler.register('et_ascolp', function(args) {
		args.fullName = 'ext.tables.columns.assignProperty';
		xajax.ext.tables.columns.assignProperty(args.data, args.id, args.pos, args.prop);
		return true;
	});
	// cells
	xajax.command.handler.register('et_ac', function(args) {
		args.fullName = 'ext.tables.cells.append';
		xajax.ext.tables.cells.append(args.data, args.id);
		return true;
	});
	xajax.command.handler.register('et_ic', function(args) {
		args.fullName = 'ext.tables.cells.insert';
		xajax.ext.tables.cells.insert(args.data, args.id, args.pos);
		return true;
	});
	xajax.command.handler.register('et_rc', function(args) {
		args.fullName = 'ext.tables.cells.replace';
		xajax.ext.tables.cells.replace(args.data, args.id, args.pos);
	});
	xajax.command.handler.register('et_dc', function(args) {
		args.fullName = 'ext.tables.cells.remove';
		xajax.ext.tables.cells.remove(args.id, args.pos);
		return true;
	});
	xajax.command.handler.register('et_asc', function(args) {
		args.fullName = 'ext.tables.cells.assign';
		xajax.ext.tables.cells.assign(args.data, args.id, args.pos);
		return true;
	});
	xajax.command.handler.register('et_ascp', function(args) {
		args.fullName = 'ext.tables.cells.assignProperty';
		xajax.ext.tables.cells.assignProperty(args.data, args.id, args.pos, args.prop);
		return true;
	});
}

installTableUpdater();
