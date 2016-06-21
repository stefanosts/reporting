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
def fcn(v, x1, x2, x3, _args):
    return calculate_coolant_temperatures(
        _args[0], v['temperature_threshold'], _args[1], 
        v['temperature_increase_when_engine_off'], v['engine_coolant_flow'], 
        v['engine_coolant_constant'], v['heat_to_engine'], 
        _args[2], _args[3], _args[4], _args[5], _args[6], 
        x1, x2, x3)

def fcn2min(params, x1, x2, x3, data, _args):
    v = params.valuesdict()
    model = fcn(v, x1, x2, x3, _args)
    return model - data

# create a set of Parameters
params = Parameters()
params.add('temperature_threshold', value=75)
params.add('temperature_increase_when_engine_off', value=0)
params.add('engine_coolant_flow', value=0.015)
params.add('engine_coolant_constant', value=0.4)
params.add('heat_to_engine', value=0.35)

def run_main():
    
    
    x1, x2, x3 = dw.fuel_consumptions.values, dw.engine_powers_out.values, dw.engine_speeds_out.values
    data = dw.engine_coolant_temperatures.values #.diff().fillna(0)

    fuel_type = d['fuel_type']
    engine_max_power = eval(d['engine_max_power'])
    engine_fuel_lower_heating_value = eval(d['engine_fuel_lower_heating_value'])
    engine_coolant_heat_capacity, engine_coolant_equiv_mass, engine_heat_capacity, engine_mass = \
        get_thermal_model_arguments(fuel_type, engine_max_power)
    
    temperature_max, initial_engine_temperature = data.max(), data[0]
    _args = [temperature_max, initial_engine_temperature, \
             engine_fuel_lower_heating_value, engine_coolant_heat_capacity, \
             engine_coolant_equiv_mass, engine_heat_capacity, engine_mass]

    
    # do fit, here with leastsq model
    _t0 = datetime.datetime.now()

    result = minimize(fcn2min, params, args=(x1, x2, x3, data, _args))
    
    _t1 = datetime.datetime.now()
    print("Elapsed time: %s"%(_t1-_t0))
    
    # calculate final result
    final = fcn(result.params, x1, x2, x3, _args)
    
    # write error report
    report_fit(result.params)
    
    # try to plot results
    try:
        import pylab
        pylab.plot(data, 'k+')
        pylab.plot(final, 'r')
        pylab.show()
    except:
        pass

def get_thermal_model_arguments(
        fuel_type, engine_max_power):
    """
    Check CO2MPAS Parametric.
    :return:
        Thermal model related empirical arguments: coolant heat capacity [kJ/kg], coolant equivalent mass [kg],
        engine heat capacity [kJ/kg], engine mass [kg].
    :rtype: float, float, float, float
    """

    eng_mass = (0.4208*engine_max_power + 60)*(1 if fuel_type == 'gasoline' else 1.1)

    # Keys: 'coolant', 'oil', 'crankcase', 'cyl_head', 'pistons', 'crankshaft'
    mval = [0.04*eng_mass, 0.055*eng_mass, 0.18*eng_mass, 0.09*eng_mass, 0.025*eng_mass, 0.08*eng_mass]
    cpval = [0.85*4186, 2090, 526, 940, 940, 526]


    weighted_eng_mass = sum(mval)
    weighted_eng_heat_capacity = sum([a*b for a,b in zip(mval, cpval)]) / weighted_eng_mass

    return cpval[0], mval[0], weighted_eng_heat_capacity, weighted_eng_mass


def calculate_coolant_temperatures(
        temperature_max, temperature_threshold, initial_engine_temperature, temperature_increase_when_engine_off,
        engine_coolant_flow, engine_coolant_constant, heat_to_engine, engine_fuel_lower_heating_value,
        engine_coolant_heat_capacity, engine_coolant_equiv_mass, engine_heat_capacity, engine_mass,
        fuel_consumptions, engine_powers_out, engine_speeds_out):
    """
    Check CO2MPAS Parametric.
    :return:
        Engine coolant temperature vector [oC].
    :rtype: np.array
    """
    tmax = temperature_max
    tthres = temperature_threshold
    t0 = initial_engine_temperature
    tgrad = temperature_increase_when_engine_off
    cflow = engine_coolant_flow
    ccnst = engine_coolant_constant
    ccp = engine_coolant_heat_capacity
    cm = engine_coolant_equiv_mass
    ecp = engine_heat_capacity
    em = engine_mass
    h2e = heat_to_engine
    flhv = engine_fuel_lower_heating_value
    fc = fuel_consumptions
    p = engine_powers_out
    n = engine_speeds_out

    def calculate_dQ(
            fc, p, t, t0, tthres, flhv, h2e, cmcp, cflow):
        if fc <= 0:
            dQ = 0
        else:
            fh = fc * flhv
            dQ = fh * h2e
            if t > tthres:
                dQ -= cmcp * ((t - t0) * cflow)
        return dQ

    t = []
    l = len(fc)

    cmcp = ccp*cm

    t_ii = t0
    for i in range(l):

        if (t_ii >= tmax) or ((t_ii > tthres) and (p[i] <= 0)):
            t_i = t_ii - ccnst * (t_ii - tthres) / (tmax - tthres)

        elif n[i] > 0:
            dQ = calculate_dQ(
                    fc[i - 1], p[i - 1], t_ii, t0, tthres, flhv, h2e, cmcp, cflow)
            t_i = t_ii + dQ / (em * ecp)

        else:
            t_i = t_ii + tgrad

        t_ii = t_i
        t.append(t_i)

    return np.array(t)


def main():
    run_main()

if __name__ == '__main__':
    main()
    
    

