import React from 'react';

class Legend extends React.Component {
  static icon(className, label) {
    return (
        <li>
          <span className={className}></span> {label}
        </li>
    );
  }

  render() {
    return (
      <div className="card mt-5" id="metric-manager-legend">
        <h4 className="card-header">
          Icon Legend
        </h4>
        <div className="card-body d-flex">
          <section>
            <h6 className="card-subtitle mb-2">
              Selectable by users
            </h6>
            <ul className="card-text">
              {Legend.icon('far fa-check-circle', 'Selectable')}
              {Legend.icon('fas fa-ban', 'Not selectable')}
            </ul>
          </section>

          <section>
            <h6 className="card-subtitle mb-2">
              Metric data type
            </h6>
            <ul className="card-text">
              {Legend.icon('far fa-chart-bar', 'Numeric')}
              {Legend.icon('far fa-thumbs-up', 'Boolean')}
            </ul>
          </section>

          <section>
            <h6 className="card-subtitle mb-2">
              Visible to users
            </h6>
            <ul className="card-text">
              {Legend.icon(
                  'far fa-eye-slash',
                  <span className="metric-hidden">Hidden</span>
              )}
            </ul>
          </section>
        </div>
      </div>
    );
  }
}

export {Legend};
