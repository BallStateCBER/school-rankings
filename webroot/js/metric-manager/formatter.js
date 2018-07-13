class Formatter {
  static formatMetricsForJsTree(data) {
    let retval = [];

    data.forEach((metric) => {
      let jTreeData = {
        text: metric.name,
        data: {
          selectable: Boolean(metric.selectable),
          type: metric.type,
          metricId: metric.id,
          name: metric.name,
          description: metric.description,
          visible: metric.visible,
        },
        a_attr: {
          'data-metric-id': metric.id,
        },
        li_attr: {
          'data-selectable': Boolean(metric.selectable) ? 1 : 0,
          'data-type': metric.type,
          'data-visible': Boolean(metric.visible) ? 1 : 0,
          'data-metric-id': metric.id,
        },
        icon: Boolean(metric.selectable) ? 'far fa-check-circle' : 'fas fa-ban',
      };
      if (metric.children.length > 0) {
        jTreeData.children = Formatter.formatMetricsForJsTree(
            metric.children
        );
      }
      retval.push(jTreeData);
    });

    return retval;
  }
}

export {Formatter};
