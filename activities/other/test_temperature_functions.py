import pandas as pd, numpy as np
from lmfit import minimize, Parameters, Parameter, report_fit
from os.path import join
import datetime

dwd = r'D:\Work\datasets.git\green_driving' # Data working directory
fn = '20160315_163135-astra_test.xlsx'
# fn = join(dwd, fn)

shts = ['nedc_predictions_time_series', 'wltp_h_predictions_time_series']

fn = r'D:\Work\reporting.git\activities\other\20160315_163135-astra_test.xlsx'

d = pd.read_excel(fn, "nedc_inputs_parameters", index_col=1).Value
dn = pd.read_excel(fn, shts[0], index_col=0, skiprows=[0,2])
dw = pd.read_excel(fn, shts[1], index_col=0, skiprows=[0,2])

# define objective function: returns the array to be minimized
def fcn(v, x1, x2, x3, t_init):
    return calculate_coolant_temperatures(
        v['temperature_threshold'], t_init, v['temperature_increase_when_engine_off'],
        x1, x2, x3, v['c1'], v['c2'], v['c3'], v['c4'], v['c5'], v['c6'])

def fcn2min(params, x1, x2, x3, data, t_init):
    v = params.valuesdict()
    model = fcn(v, x1, x2, x3, t_init)
    return model - data

# create a set of Parameters
params = Parameters()
params.add('temperature_threshold', value=75)
params.add('temperature_increase_when_engine_off', value=0)
params.add('c1', value=1)
params.add('c2', value=0.33)
params.add('c3', value=-0.01)
params.add('c4', value=1)
params.add('c5', value=0.1)
params.add('c6', value=0.1)

def run_main():
    
    
    x1, x2, x3 = dw.fuel_consumptions.values, dw.engine_speeds_out.values, dw.velocities.values
    data = dw.engine_coolant_temperatures.values #.diff().fillna(0)

    temperature_threshold = 95
    temperature_max, initial_engine_temperature = data.max(), data[0]
    t_init = initial_engine_temperature 

    
    # do fit, here with leastsq model
    _t0 = datetime.datetime.now()

    result = minimize(fcn2min, params, args=(x1, x2, x3, data, t_init))
    
    _t1 = datetime.datetime.now()
    print("Elapsed time: %s"%(_t1-_t0))
    
    # calculate final result
    final = fcn(result.params, x1, x2, x3, t_init)
    
    # write error report
    report_fit(result.params)
    
    
    
    n1, n2, n3, = dn.fuel_consumptions.values, dn.engine_speeds_out.values, dn.velocities.values
    nedc_data = dn.engine_coolant_temperatures.values
    nedc_t_init = nedc_data[0]
    nedc_final = fcn(result.params, n1, n2, n3, nedc_t_init)

    # try to plot results
    try:
        import pylab
        pylab.plot(data, 'k+')
        pylab.plot(final, 'r')
        pylab.plot(nedc_data, 'g+')
        pylab.plot(nedc_final, 'b')
        pylab.show()
    except:
        pass
    

def calculate_coolant_temperatures(
        temperature_threshold, initial_engine_temperature, temperature_increase_when_engine_off,
        fuel_consumptions, engine_speeds_out, velocities, c1, c2, c3, c4, c5, c6):
    """
    Check CO2MPAS Parametric.
    :return:
        Engine coolant temperature vector [oC].
    :rtype: np.array
    """
    tthres = temperature_threshold
    t0 = initial_engine_temperature
    tgrad = temperature_increase_when_engine_off
    fc = fuel_consumptions
    n = engine_speeds_out
    v = velocities

    def calculate_dQ(
            fc, t, tthres, v, e0, ie0, c2, c3, c4, c5, c6):
        if fc <= 0:
            dQ = 0; e = e0; ie = ie0
        else:
            dQ = c2 * fc
            dQ -= c3 * (v/3600)**2 # Check using accelerations instead of velocities
            if t > tthres:
                e = t - tthres
                de = e - e0 # This will work only in 1Hz, we need time
                ie = e + ie0
                dQ -= c4 * e + c5 * de + c6 * ie
            else:
                e = e0
                ie = ie0
                
        return dQ, e, ie

    t = []
    l = len(fc)

    t_ii = t0
    e, ie = 0, 0
    for i in range(l):

        if n[i] > 0:
            if t_ii == tthres: ie = 0
            dQ, e, ie = calculate_dQ(
                    fc[i - 1], t_ii, tthres, v[i - 1], e, ie, c2, c3, c4, c5, c6)
        
            t_i = t_ii + dQ / c1

        else:
            t_i = t_ii + tgrad * t_ii

        t_ii = t_i
        t.append(t_i)

    return np.array(t)


def main():
    run_main()

if __name__ == '__main__':
    main()
    
    

